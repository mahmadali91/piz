<?php
declare(strict_types=1);

namespace PIZ\Models;

use PIZ\Database;
use PIZ\Settings;

final class Sale
{
    public const PAYMENT_METHODS = ['cash', 'card', 'transfer'];

    /**
     * Создаёт продажу в транзакции: проверяет остатки, фиксирует цены и себестоимость,
     * списывает товар и пишет движения по складу.
     *
     * @param array<int, array{id: int|string, qty: int|string}> $rawItems
     * @return array{sale: array, duplicate: bool}
     */
    public static function create(array $rawItems, array $opts, int $userId): array
    {
        $payment = (string) ($opts['payment_method'] ?? 'cash');
        if (!in_array($payment, self::PAYMENT_METHODS, true)) {
            throw new \InvalidArgumentException('Выберите способ оплаты.');
        }

        $token = trim((string) ($opts['token'] ?? ''));
        if ($token !== '') {
            if (mb_strlen($token) > 64) {
                $token = mb_substr($token, 0, 64);
            }
            $existing = self::findByToken($token);
            if ($existing) {
                return ['sale' => $existing, 'duplicate' => true];
            }
        }

        $items = [];
        foreach ($rawItems as $item) {
            $productId = (int) ($item['id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                throw new \InvalidArgumentException('Некорректный состав продажи.');
            }
            if ($qty > 999) {
                throw new \InvalidArgumentException('Слишком большое количество одного товара.');
            }
            $items[$productId] = ($items[$productId] ?? 0) + $qty;
        }
        if ($items === []) {
            throw new \InvalidArgumentException('Добавьте хотя бы один товар в чек.');
        }
        if (count($items) > 200) {
            throw new \InvalidArgumentException('В чеке слишком много позиций.');
        }

        $customer = trim((string) ($opts['customer'] ?? ''));
        $comment = trim((string) ($opts['comment'] ?? ''));
        if (mb_strlen($customer) > 120) {
            throw new \InvalidArgumentException('Имя покупателя слишком длинное.');
        }
        if (mb_strlen($comment) > 255) {
            throw new \InvalidArgumentException('Комментарий слишком длинный.');
        }

        $discount = max(0, (int) ($opts['discount_kop'] ?? 0));
        $allowNegative = Settings::get('allow_negative_stock', '1') === '1';
        $shift = Shift::ensureOpen($userId);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($items), '?'));
            $rows = Database::fetchAll(
                "SELECT * FROM products WHERE id IN ({$placeholders}) FOR UPDATE",
                array_keys($items)
            );
            $products = [];
            foreach ($rows as $row) {
                $products[(int) $row['id']] = $row;
            }

            $total = 0;
            $cost = 0;
            $prepared = [];
            foreach ($items as $productId => $qty) {
                if (!isset($products[$productId])) {
                    throw new \RuntimeException('Один из товаров не найден. Обновите страницу.');
                }
                $product = $products[$productId];
                if ((int) $product['active'] !== 1) {
                    throw new \RuntimeException('Товар «' . $product['name'] . '» снят с продажи.');
                }
                $stock = (int) $product['stock_qty'];
                if (!$allowNegative && $stock < $qty) {
                    throw new \RuntimeException(
                        '«' . $product['name'] . '»: в наличии ' . $stock . ', а в чеке ' . $qty . '. Уменьшите количество.'
                    );
                }
                $price = (int) $product['sale_price_kop'];
                $unitCost = (int) $product['purchase_price_kop'];
                $total += $price * $qty;
                $cost += $unitCost * $qty;
                $prepared[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'price' => $price,
                    'cost' => $unitCost,
                    'total' => $price * $qty,
                ];
            }

            $discount = min($discount, $total);
            $payable = $total - $discount;

            $received = $payable;
            $change = 0;
            if ($payment === 'cash') {
                $received = array_key_exists('received_kop', $opts) && $opts['received_kop'] !== null
                    ? (int) $opts['received_kop']
                    : $payable;
                if ($received < $payable) {
                    throw new \RuntimeException('Получено меньше суммы к оплате.');
                }
                $change = $received - $payable;
            }

            $tempNumber = 'T' . bin2hex(random_bytes(8));
            Database::query(
                'INSERT INTO sales (number, user_id, total_kop, cost_kop, discount_kop, payment_method, received_kop, change_kop, customer, comment, status, shift_id, client_token, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "completed", ?, ?, ?)',
                [
                    $tempNumber,
                    $userId,
                    $payable,
                    $cost,
                    $discount,
                    $payment,
                    $received,
                    $change,
                    $customer !== '' ? $customer : null,
                    $comment !== '' ? $comment : null,
                    (int) $shift['id'],
                    $token !== '' ? $token : null,
                    now(),
                ]
            );
            $saleId = (int) $pdo->lastInsertId();
            $number = date('ymd') . '-' . str_pad((string) $saleId, 4, '0', STR_PAD_LEFT);
            Database::query('UPDATE sales SET number = ? WHERE id = ?', [$number, $saleId]);

            foreach ($prepared as $line) {
                $product = $line['product'];
                Database::query(
                    'INSERT INTO sale_items (sale_id, product_id, name, qty, price_kop, cost_kop, total_kop) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$saleId, (int) $product['id'], $product['name'], $line['qty'], $line['price'], $line['cost'], $line['total']]
                );

                $after = (int) $product['stock_qty'] - $line['qty'];
                Database::query('UPDATE products SET stock_qty = ?, updated_at = ? WHERE id = ?', [$after, now(), (int) $product['id']]);
                Database::query(
                    'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, sale_id, user_id, created_at)
                     VALUES (?, "sale", ?, ?, ?, ?, ?, ?, ?)',
                    [(int) $product['id'], -$line['qty'], $after, $line['cost'], 'Продажа ' . $number, $saleId, $userId, now()]
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($token !== '' && self::isDuplicateKey($e)) {
                $existing = self::findByToken($token);
                if ($existing) {
                    return ['sale' => $existing, 'duplicate' => true];
                }
            }
            throw $e;
        }

        $sale = self::find($saleId);
        if (!$sale) {
            throw new \RuntimeException('Не удалось загрузить сохранённую продажу.');
        }
        return ['sale' => $sale, 'duplicate' => false];
    }

    private static function isDuplicateKey(\Throwable $e): bool
    {
        return $e instanceof \PDOException && (string) $e->getCode() === '23000';
    }

    public static function find(int $id): ?array
    {
        $sale = Database::fetch(
            'SELECT s.*, u.name AS cashier_name, u.login AS cashier_login,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS items_count
             FROM sales s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.id = ?',
            [$id]
        );
        if (!$sale) {
            return null;
        }
        $sale['items'] = Database::fetchAll('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id ASC', [$id]);
        return $sale;
    }

    public static function findByToken(string $token): ?array
    {
        $row = Database::fetch('SELECT id FROM sales WHERE client_token = ? LIMIT 1', [$token]);
        return $row ? self::find((int) $row['id']) : null;
    }

    public static function list(array $filters, int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = self::buildWhere($filters);
        $limit = max(1, min(5000, $limit));
        $offset = max(0, $offset);
        return Database::fetchAll(
            "SELECT s.*, u.name AS cashier_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS items_count
             FROM sales s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE {$where}
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function count(array $filters): int
    {
        [$where, $params] = self::buildWhere($filters);
        return (int) Database::fetchValue("SELECT COUNT(*) FROM sales s WHERE {$where}", $params);
    }

    /**
     * Итоги по тем же фильтрам, что и список (без учёта пагинации).
     *
     * @return array{count: int, completed: int, canceled: int, revenue: int, cost: int, profit: int, discount: int}
     */
    public static function summary(array $filters): array
    {
        [$where, $params] = self::buildWhere($filters);
        $row = Database::fetch(
            "SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
                    COALESCE(SUM(CASE WHEN s.status = 'canceled' THEN 1 ELSE 0 END), 0) AS canceled,
                    COALESCE(SUM(CASE WHEN s.status = 'completed' THEN s.total_kop ELSE 0 END), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN s.status = 'completed' THEN s.cost_kop ELSE 0 END), 0) AS cost,
                    COALESCE(SUM(CASE WHEN s.status = 'completed' THEN s.discount_kop ELSE 0 END), 0) AS discount
             FROM sales s WHERE {$where}",
            $params
        );
        $revenue = (int) ($row['revenue'] ?? 0);
        $cost = (int) ($row['cost'] ?? 0);
        return [
            'count' => (int) ($row['cnt'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'canceled' => (int) ($row['canceled'] ?? 0),
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $revenue - $cost,
            'discount' => (int) ($row['discount'] ?? 0),
        ];
    }

    /** @return array{0: string, 1: array} */
    private static function buildWhere(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (!empty($filters['from'])) {
            $conditions[] = 's.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $conditions[] = 's.created_at < ?';
            $params[] = date('Y-m-d 00:00:00', strtotime($filters['to'] . ' +1 day'));
        }
        if (!empty($filters['payment']) && in_array($filters['payment'], self::PAYMENT_METHODS, true)) {
            $conditions[] = 's.payment_method = ?';
            $params[] = $filters['payment'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['completed', 'canceled'], true)) {
            $conditions[] = 's.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(s.number LIKE ? OR s.customer LIKE ?)';
            $needle = '%' . $filters['search'] . '%';
            $params[] = $needle;
            $params[] = $needle;
        }
        if (!empty($filters['user_id'])) {
            $conditions[] = 's.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        return [implode(' AND ', $conditions), $params];
    }

    public static function cancel(int $id, int $userId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $sale = Database::fetch('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$id]);
            if (!$sale) {
                throw new \RuntimeException('Продажа не найдена.');
            }
            if ($sale['status'] === 'canceled') {
                throw new \RuntimeException('Эта продажа уже отменена.');
            }

            $items = Database::fetchAll('SELECT * FROM sale_items WHERE sale_id = ?', [$id]);
            foreach ($items as $item) {
                if ($item['product_id'] === null) {
                    continue;
                }
                $productId = (int) $item['product_id'];
                $product = Database::fetch('SELECT id FROM products WHERE id = ? FOR UPDATE', [$productId]);
                if (!$product) {
                    continue;
                }
                Database::query(
                    'UPDATE products SET stock_qty = stock_qty + ?, updated_at = ? WHERE id = ?',
                    [(int) $item['qty'], now(), $productId]
                );
                $after = (int) Database::fetchValue('SELECT stock_qty FROM products WHERE id = ?', [$productId]);
                Database::query(
                    'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, sale_id, user_id, created_at)
                     VALUES (?, "cancel", ?, ?, ?, ?, ?, ?, ?)',
                    [$productId, (int) $item['qty'], $after, (int) $item['cost_kop'], 'Возврат по чеку ' . $sale['number'], $id, $userId, now()]
                );
            }

            Database::query(
                'UPDATE sales SET status = "canceled", canceled_at = ?, canceled_by = ? WHERE id = ?',
                [now(), $userId, $id]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function totalsForDate(string $date): array
    {
        $start = $date . ' 00:00:00';
        $end = date('Y-m-d 00:00:00', strtotime($date . ' +1 day'));
        return self::rangeTotals($start, $end);
    }

    /**
     * @return array{count: int, revenue: int, cost: int, discount: int, profit: int, avg_check: int}
     */
    public static function rangeTotals(string $start, string $endExclusive): array
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(total_kop), 0) AS revenue,
                    COALESCE(SUM(cost_kop), 0) AS cost,
                    COALESCE(SUM(discount_kop), 0) AS discount
             FROM sales
             WHERE status = "completed" AND created_at >= ? AND created_at < ?',
            [$start, $endExclusive]
        );
        $count = (int) ($row['cnt'] ?? 0);
        $revenue = (int) ($row['revenue'] ?? 0);
        $cost = (int) ($row['cost'] ?? 0);
        $expense = (int) Database::fetchValue(
            'SELECT COALESCE(SUM(total_kop), 0) FROM writeoffs WHERE created_at >= ? AND created_at < ?',
            [$start, $endExclusive]
        );
        return [
            'count' => $count,
            'revenue' => $revenue,
            'cost' => $cost,
            'discount' => (int) ($row['discount'] ?? 0),
            'expense' => $expense,
            'gross_profit' => $revenue - $cost,
            'profit' => $revenue - $cost - $expense,
            'avg_check' => $count > 0 ? (int) round($revenue / $count) : 0,
        ];
    }

    /**
     * Ряд по дням (или по месяцам для длинных периодов).
     *
     * @return array<string, array{revenue: int, profit: int, count: int}>
     */
    public static function dailySeries(string $start, string $endExclusive): array
    {
        $spanDays = max(1, (int) round((strtotime($endExclusive) - strtotime($start)) / 86400));
        $monthly = $spanDays > 120;
        $groupExpression = $monthly ? "DATE_FORMAT(created_at, '%Y-%m-01')" : 'DATE(created_at)';

        $rows = Database::fetchAll(
            "SELECT {$groupExpression} AS d,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(total_kop), 0) AS revenue,
                    COALESCE(SUM(cost_kop), 0) AS cost
             FROM sales
             WHERE status = 'completed' AND created_at >= ? AND created_at < ?
             GROUP BY {$groupExpression}",
            [$start, $endExclusive]
        );

        $expenseRows = Database::fetchAll(
            "SELECT {$groupExpression} AS d, COALESCE(SUM(total_kop), 0) AS expense
             FROM writeoffs
             WHERE created_at >= ? AND created_at < ?
             GROUP BY {$groupExpression}",
            [$start, $endExclusive]
        );
        $expenses = [];
        foreach ($expenseRows as $row) {
            $expenses[(string) $row['d']] = (int) $row['expense'];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['d']] = [
                'count' => (int) $row['cnt'],
                'revenue' => (int) $row['revenue'],
                'profit' => (int) $row['revenue'] - (int) $row['cost'] - ($expenses[(string) $row['d']] ?? 0),
                'expense' => $expenses[(string) $row['d']] ?? 0,
            ];
        }

        $series = [];
        $end = strtotime($endExclusive);
        if ($monthly) {
            $cursor = strtotime(date('Y-m-01', strtotime($start)));
            while ($cursor < $end) {
                $date = date('Y-m-01', $cursor);
                $series[$date] = $map[$date] ?? ['count' => 0, 'revenue' => 0, 'profit' => -($expenses[$date] ?? 0), 'expense' => $expenses[$date] ?? 0];
                $cursor = strtotime('+1 month', $cursor);
            }
            return $series;
        }

        $cursor = strtotime($start);
        while ($cursor < $end) {
            $date = date('Y-m-d', $cursor);
            $series[$date] = $map[$date] ?? ['count' => 0, 'revenue' => 0, 'profit' => -($expenses[$date] ?? 0), 'expense' => $expenses[$date] ?? 0];
            $cursor = strtotime('+1 day', $cursor);
        }
        return $series;
    }

    /** @return array<string, array{count: int, revenue: int}> */
    public static function paymentBreakdown(string $start, string $endExclusive): array
    {
        $rows = Database::fetchAll(
            'SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(total_kop), 0) AS revenue
             FROM sales
             WHERE status = "completed" AND created_at >= ? AND created_at < ?
             GROUP BY payment_method',
            [$start, $endExclusive]
        );
        $result = [];
        foreach (self::PAYMENT_METHODS as $method) {
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

    public static function topProducts(string $start, string $endExclusive, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        return Database::fetchAll(
            "SELECT si.product_id, si.name,
                    SUM(si.qty) AS qty,
                    SUM(si.total_kop) AS revenue,
                    SUM(si.qty * si.cost_kop) AS cost
             FROM sale_items si
             INNER JOIN sales s ON s.id = si.sale_id
             WHERE s.status = 'completed' AND s.created_at >= ? AND s.created_at < ?
             GROUP BY si.product_id, si.name
             ORDER BY revenue DESC
             LIMIT {$limit}",
            [$start, $endExclusive]
        );
    }

    public static function recent(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        return Database::fetchAll(
            "SELECT s.*, u.name AS cashier_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS items_count
             FROM sales s
             LEFT JOIN users u ON u.id = s.user_id
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT {$limit}"
        );
    }

    public static function canceledCount(string $start, string $endExclusive): int
    {
        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM sales WHERE status = "canceled" AND created_at >= ? AND created_at < ?',
            [$start, $endExclusive]
        );
    }

    /**
     * Нормализует границы периода (включительно по датам).
     *
     * @return array{0: string, 1: string} [начало, конец (не включительно)]
     */
    public static function bounds(?string $from, ?string $to): array
    {
        $from = self::validDate($from) ?? date('Y-m-01');
        $to = self::validDate($to) ?? today();
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        return [$from . ' 00:00:00', date('Y-m-d 00:00:00', strtotime($to . ' +1 day'))];
    }

    public static function validDate(?string $date): ?string
    {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        return checkdate($m, $d, $y) ? $date : null;
    }
}
