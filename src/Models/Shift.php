<?php
declare(strict_types=1);

namespace PIZ\Models;

use PIZ\Database;

final class Shift
{
    public static function current(): ?array
    {
        $row = Database::fetch(
            "SELECT s.*, u.name AS opened_by_name
             FROM shifts s
             LEFT JOIN users u ON u.id = s.opened_by
             WHERE s.status = 'open'
             ORDER BY s.id DESC
             LIMIT 1"
        );
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $row = Database::fetch(
            'SELECT s.*, uo.name AS opened_by_name, uc.name AS closed_by_name
             FROM shifts s
             LEFT JOIN users uo ON uo.id = s.opened_by
             LEFT JOIN users uc ON uc.id = s.closed_by
             WHERE s.id = ?',
            [$id]
        );
        return $row ?: null;
    }

    public static function open(int $userId): array
    {
        if (self::current() !== null) {
            throw new \RuntimeException('Смена уже открыта. Сначала закройте текущую.');
        }
        Database::query('INSERT INTO shifts (opened_by, opened_at, status) VALUES (?, ?, "open")', [$userId, now()]);
        $id = (int) Database::pdo()->lastInsertId();
        $shift = self::find($id);
        if (!$shift) {
            throw new \RuntimeException('Не удалось открыть смену.');
        }
        return $shift;
    }

    /** Открывает смену автоматически при первой продаже/списании. */
    public static function ensureOpen(int $userId): array
    {
        $current = self::current();
        if ($current !== null) {
            return $current;
        }
        return self::open($userId);
    }

    public static function close(int $id, int $userId, string $note, bool $isAdmin): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $shift = Database::fetch('SELECT * FROM shifts WHERE id = ? FOR UPDATE', [$id]);
            if (!$shift) {
                throw new \RuntimeException('Смена не найдена.');
            }
            if ($shift['status'] !== 'open') {
                throw new \RuntimeException('Смена уже закрыта.');
            }
            if (!$isAdmin && (int) $shift['opened_by'] !== $userId) {
                throw new \RuntimeException('Закрыть смену может её открывший или администратор.');
            }

            $totals = self::totals($id);
            $stock = Database::fetch(
                'SELECT COALESCE(SUM(stock_qty), 0) AS units,
                        COALESCE(SUM(stock_qty * purchase_price_kop), 0) AS value
                 FROM products WHERE active = 1'
            );
            $products = Database::fetchAll(
                'SELECT id, name, sku, stock_qty, purchase_price_kop, sale_price_kop
                 FROM products WHERE active = 1 ORDER BY name ASC'
            );
            foreach ($products as $product) {
                Database::query(
                    'INSERT INTO shift_stock (shift_id, product_id, name, sku, qty, unit_cost_kop, sale_price_kop, stock_value_kop)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $id,
                        (int) $product['id'],
                        $product['name'],
                        $product['sku'],
                        (int) $product['stock_qty'],
                        (int) $product['purchase_price_kop'],
                        (int) $product['sale_price_kop'],
                        (int) $product['stock_qty'] * (int) $product['purchase_price_kop'],
                    ]
                );
            }

            Database::query(
                'UPDATE shifts SET closed_by = ?, closed_at = ?, status = "closed", note = ?,
                    sales_count = ?, revenue_kop = ?, cost_kop = ?, expense_kop = ?, profit_kop = ?,
                    stock_units = ?, stock_value_kop = ?
                 WHERE id = ?',
                [
                    $userId,
                    now(),
                    mb_substr(trim($note), 0, 255),
                    $totals['count'],
                    $totals['revenue'],
                    $totals['cost'],
                    $totals['expense'],
                    $totals['profit'],
                    (int) ($stock['units'] ?? 0),
                    (int) ($stock['value'] ?? 0),
                    $id,
                ]
            );

            $pdo->commit();
            return self::find($id) ?? [];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array{count:int, revenue:int, cost:int, expense:int, profit:int} */
    public static function totals(int $shiftId): array
    {
        $sales = Database::fetch(
            'SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(total_kop), 0) AS revenue,
                    COALESCE(SUM(cost_kop), 0) AS cost
             FROM sales WHERE shift_id = ? AND status = "completed"',
            [$shiftId]
        );
        $expense = (int) Database::fetchValue(
            'SELECT COALESCE(SUM(total_kop), 0) FROM writeoffs WHERE shift_id = ?',
            [$shiftId]
        );
        $revenue = (int) ($sales['revenue'] ?? 0);
        $cost = (int) ($sales['cost'] ?? 0);
        return [
            'count' => (int) ($sales['cnt'] ?? 0),
            'revenue' => $revenue,
            'cost' => $cost,
            'expense' => $expense,
            'profit' => $revenue - $cost - $expense,
        ];
    }

    /** @return array<string, array{count:int, revenue:int}> */
    public static function payments(int $shiftId): array
    {
        $rows = Database::fetchAll(
            'SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(total_kop), 0) AS revenue
             FROM sales WHERE shift_id = ? AND status = "completed"
             GROUP BY payment_method',
            [$shiftId]
        );
        $result = [];
        foreach (Sale::PAYMENT_METHODS as $method) {
            $result[$method] = ['count' => 0, 'revenue' => 0];
        }
        foreach ($rows as $row) {
            $result[(string) $row['payment_method']] = [
                'count' => (int) $row['cnt'],
                'revenue' => (int) $row['revenue'],
            ];
        }
        return $result;
    }

    public static function stock(int $shiftId): array
    {
        return Database::fetchAll(
            'SELECT * FROM shift_stock WHERE shift_id = ? ORDER BY name ASC',
            [$shiftId]
        );
    }

    public static function writeoffs(int $shiftId): array
    {
        return Database::fetchAll(
            'SELECT w.*, p.name AS product_name, p.unit, u.name AS user_name
             FROM writeoffs w
             LEFT JOIN products p ON p.id = w.product_id
             LEFT JOIN users u ON u.id = w.user_id
             WHERE w.shift_id = ?
             ORDER BY w.created_at ASC',
            [$shiftId]
        );
    }

    public static function list(int $limit = 30, int $offset = 0, ?int $onlyUserId = null): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $where = '1 = 1';
        $params = [];
        if ($onlyUserId !== null) {
            $where .= ' AND s.opened_by = ?';
            $params[] = $onlyUserId;
        }
        return Database::fetchAll(
            "SELECT s.*, uo.name AS opened_by_name, uc.name AS closed_by_name
             FROM shifts s
             LEFT JOIN users uo ON uo.id = s.opened_by
             LEFT JOIN users uc ON uc.id = s.closed_by
             WHERE {$where}
             ORDER BY s.opened_at DESC, s.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function count(?int $onlyUserId = null): int
    {
        if ($onlyUserId !== null) {
            return (int) Database::fetchValue('SELECT COUNT(*) FROM shifts WHERE opened_by = ?', [$onlyUserId]);
        }
        return (int) Database::fetchValue('SELECT COUNT(*) FROM shifts');
    }

    public static function stockTotals(int $shiftId): array
    {
        $row = Database::fetch(
            'SELECT COALESCE(SUM(qty), 0) AS units, COALESCE(SUM(stock_value_kop), 0) AS value
             FROM shift_stock WHERE shift_id = ?',
            [$shiftId]
        );
        return ['units' => (int) ($row['units'] ?? 0), 'value' => (int) ($row['value'] ?? 0)];
    }
}
