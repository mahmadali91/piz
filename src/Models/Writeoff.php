<?php
declare(strict_types=1);

namespace PIZ\Models;

use PIZ\Database;
use PIZ\Settings;

final class Writeoff
{
    public const REASONS = [
        'Сотрудник' => 'Сотрудник (питание/использование)',
        'Личное использование' => 'Личное использование',
        'Брак' => 'Брак / порча',
        'Другое' => 'Другое',
    ];

    /**
     * Списание товара: уменьшает остаток и учитывается как расход, а не как продажа.
     */
    public static function create(
        int $productId,
        int $qty,
        string $employeeName,
        string $reason,
        string $comment,
        int $userId
    ): int {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Количество должно быть больше нуля.');
        }
        if ($qty > 100000) {
            throw new \InvalidArgumentException('Слишком большое количество.');
        }
        $reason = trim($reason);
        if (!array_key_exists($reason, self::REASONS)) {
            $reason = 'Другое';
        }
        $employeeName = mb_substr(trim($employeeName), 0, 120);
        $comment = mb_substr(trim($comment), 0, 255);

        $allowNegative = Settings::get('allow_negative_stock', '1') === '1';
        $shift = Shift::ensureOpen($userId);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $product = Database::fetch('SELECT * FROM products WHERE id = ? FOR UPDATE', [$productId]);
            if (!$product) {
                throw new \RuntimeException('Товар не найден.');
            }
            if ((int) $product['active'] !== 1) {
                throw new \RuntimeException('Товар снят с продажи.');
            }
            $stock = (int) $product['stock_qty'];
            if (!$allowNegative && $stock < $qty) {
                throw new \RuntimeException('На складе только ' . $stock . ' ' . $product['unit'] . '.');
            }

            $cost = (int) $product['purchase_price_kop'];
            $total = $cost * $qty;
            $after = $stock - $qty;

            Database::query('UPDATE products SET stock_qty = ?, updated_at = ? WHERE id = ?', [$after, now(), $productId]);

            Database::query(
                'INSERT INTO writeoffs (product_id, qty, unit_cost_kop, total_kop, employee_name, reason, comment, user_id, shift_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $productId,
                    $qty,
                    $cost,
                    $total,
                    $employeeName !== '' ? $employeeName : null,
                    $reason,
                    $comment !== '' ? $comment : null,
                    $userId,
                    (int) $shift['id'],
                    now(),
                ]
            );
            $writeoffId = (int) $pdo->lastInsertId();

            Database::query(
                'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                 VALUES (?, "writeoff", ?, ?, ?, ?, ?, ?)',
                [
                    $productId,
                    -$qty,
                    $after,
                    $cost,
                    'Списание: ' . $reason . ($employeeName !== '' ? ' — ' . $employeeName : ''),
                    $userId,
                    now(),
                ]
            );

            $pdo->commit();
            return $writeoffId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function list(int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        return Database::fetchAll(
            "SELECT w.*, p.name AS product_name, p.unit, u.name AS user_name
             FROM writeoffs w
             LEFT JOIN products p ON p.id = w.product_id
             LEFT JOIN users u ON u.id = w.user_id
             ORDER BY w.created_at DESC, w.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
    }

    public static function count(): int
    {
        return (int) Database::fetchValue('SELECT COUNT(*) FROM writeoffs');
    }

    public static function totalForRange(string $start, string $endExclusive): int
    {
        return (int) Database::fetchValue(
            'SELECT COALESCE(SUM(total_kop), 0) FROM writeoffs WHERE created_at >= ? AND created_at < ?',
            [$start, $endExclusive]
        );
    }

    public static function qtyForRange(string $start, string $endExclusive): int
    {
        return (int) Database::fetchValue(
            'SELECT COALESCE(SUM(qty), 0) FROM writeoffs WHERE created_at >= ? AND created_at < ?',
            [$start, $endExclusive]
        );
    }

    public static function recent(int $limit = 5): array
    {
        return self::list(max(1, $limit));
    }
}
