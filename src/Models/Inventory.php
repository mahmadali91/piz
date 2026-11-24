<?php
declare(strict_types=1);

namespace PIZ\Models;

use PIZ\Database;

final class Inventory
{
    public static function all(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Database::fetchAll(
            "SELECT i.*, u.name AS started_by_name, c.name AS completed_by_name
             FROM inventory_sessions i
             LEFT JOIN users u ON u.id = i.started_by
             LEFT JOIN users c ON c.id = i.completed_by
             ORDER BY i.started_at DESC, i.id DESC
             LIMIT {$limit}"
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT i.*, u.name AS started_by_name, c.name AS completed_by_name
             FROM inventory_sessions i
             LEFT JOIN users u ON u.id = i.started_by
             LEFT JOIN users c ON c.id = i.completed_by
             WHERE i.id = ?',
            [$id]
        );
    }

    public static function lines(int $sessionId): array
    {
        return Database::fetchAll(
            'SELECT * FROM inventory_lines WHERE session_id = ? ORDER BY name ASC',
            [$sessionId]
        );
    }

    public static function create(string $warehouse, string $note, int $userId): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $prefix = 'ИНВ-' . date('Ymd') . '-';
            $last = Database::fetchValue('SELECT number FROM inventory_sessions WHERE number LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
            $seq = 1;
            if ($last) {
                $tail = substr((string) $last, strlen($prefix));
                if (ctype_digit($tail)) {
                    $seq = (int) $tail + 1;
                }
            }
            $number = $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            Database::query(
                'INSERT INTO inventory_sessions (number, warehouse, status, started_by, started_at, note)
                 VALUES (?, ?, "draft", ?, ?, ?)',
                [$number, $warehouse, $userId, now(), $note ?: null]
            );
            $sessionId = (int) $pdo->lastInsertId();
            $products = Database::fetchAll('SELECT id, name, sku, unit, stock_qty, purchase_price_kop FROM products WHERE active = 1 ORDER BY name ASC');
            $totalProducts = 0;
            $totalQty = 0;
            foreach ($products as $product) {
                Database::query(
                    'INSERT INTO inventory_lines (session_id, product_id, name, sku, unit, calculated_qty, actual_qty, diff_qty, cost_kop, diff_value_kop)
                     VALUES (?, ?, ?, ?, ?, ?, NULL, 0, ?, 0)',
                    [
                        $sessionId,
                        (int) $product['id'],
                        $product['name'],
                        $product['sku'],
                        $product['unit'],
                        (int) $product['stock_qty'],
                        (int) $product['purchase_price_kop'],
                    ]
                );
                $totalProducts++;
                $totalQty += (int) $product['stock_qty'];
            }
            Database::query('UPDATE inventory_sessions SET total_products = ?, total_calculated = ? WHERE id = ?', [$totalProducts, $totalQty, $sessionId]);
            $pdo->commit();
            return $sessionId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<int, int> $actuals product_id => actual qty */
    public static function saveLines(int $sessionId, array $actuals): void
    {
        $session = self::find($sessionId);
        if (!$session || $session['status'] !== 'draft') {
            throw new \RuntimeException('Инвентаризация уже завершена или не найдена.');
        }
        $lines = self::lines($sessionId);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $totalActual = 0;
            $totalDiff = 0;
            $totalValue = 0;
            foreach ($lines as $line) {
                $id = (int) $line['id'];
                $actual = array_key_exists((int) $line['product_id'], $actuals)
                    ? (int) $actuals[(int) $line['product_id']]
                    : (int) $line['calculated_qty'];
                $diff = $actual - (int) $line['calculated_qty'];
                $value = $diff * (int) $line['cost_kop'];
                Database::query(
                    'UPDATE inventory_lines SET actual_qty = ?, diff_qty = ?, diff_value_kop = ? WHERE id = ?',
                    [$actual, $diff, $value, $id]
                );
                $totalActual += $actual;
                $totalDiff += $diff;
                $totalValue += $value;
            }
            Database::query(
                'UPDATE inventory_sessions SET total_actual = ?, total_diff = ?, total_value_kop = ? WHERE id = ?',
                [$totalActual, $totalDiff, $totalValue, $sessionId]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function complete(int $sessionId, int $userId, string $note = ''): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $session = Database::fetch('SELECT * FROM inventory_sessions WHERE id = ? FOR UPDATE', [$sessionId]);
            if (!$session) {
                throw new \RuntimeException('Инвентаризация не найдена.');
            }
            if ($session['status'] !== 'draft') {
                throw new \RuntimeException('Инвентаризация уже завершена.');
            }
            $lines = self::lines($sessionId);
            $totalActual = 0;
            $totalDiff = 0;
            $totalValue = 0;
            foreach ($lines as $line) {
                $actual = $line['actual_qty'] !== null ? (int) $line['actual_qty'] : (int) $line['calculated_qty'];
                $diff = $actual - (int) $line['calculated_qty'];
                $value = $diff * (int) $line['cost_kop'];
                Database::query(
                    'UPDATE inventory_lines SET actual_qty = ?, diff_qty = ?, diff_value_kop = ? WHERE id = ?',
                    [$actual, $diff, $value, (int) $line['id']]
                );
                $product = Database::fetch('SELECT stock_qty, purchase_price_kop FROM products WHERE id = ? FOR UPDATE', [(int) $line['product_id']]);
                if ($product) {
                    $current = (int) $product['stock_qty'];
                    $delta = $actual - $current;
                    if ($delta !== 0) {
                        Database::query('UPDATE products SET stock_qty = ?, updated_at = ? WHERE id = ?', [$actual, now(), (int) $line['product_id']]);
                        Database::query(
                            'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                             VALUES (?, "inventory", ?, ?, ?, ?, ?, ?)',
                            [
                                (int) $line['product_id'],
                                $delta,
                                $actual,
                                (int) $line['cost_kop'],
                                'Инвентаризация ' . $session['number'],
                                $userId,
                                now(),
                            ]
                        );
                    }
                }
                $totalActual += $actual;
                $totalDiff += $diff;
                $totalValue += $value;
            }
            Database::query(
                'UPDATE inventory_sessions SET status="completed", completed_by=?, completed_at=?,
                    total_actual=?, total_diff=?, total_value_kop=?, note=COALESCE(NULLIF(?, ""), note)
                 WHERE id=?',
                [$userId, now(), $totalActual, $totalDiff, $totalValue, $note, $sessionId]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function count(): int
    {
        return (int) Database::fetchValue('SELECT COUNT(*) FROM inventory_sessions');
    }
}
