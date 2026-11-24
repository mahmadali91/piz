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
        $ids = self::createMany([
            ['product_id' => $productId, 'qty' => $qty],
        ], $employeeName, $reason, $comment, $userId);
        return $ids[0];
    }

    /**
     * Несколько списаний одной операцией: всё или ничего.
     *
     * @param array<int, array{product_id:int, qty:int}> $items
     * @return array<int, int> ID созданных списаний
     */
    public static function createMany(
        array $items,
        string $employeeName,
        string $reason,
        string $comment,
        int $userId
    ): array {
        if ($items === []) {
            throw new \InvalidArgumentException('Добавьте хотя бы один товар.');
        }
        $reason = trim($reason);
        if (!array_key_exists($reason, self::REASONS)) {
            $reason = 'Другое';
        }
        $employeeName = mb_substr(trim($employeeName), 0, 120);
        $comment = mb_substr(trim($comment), 0, 255);

        $normalized = [];
        foreach ($items as $item) {
            $qty = (int) ($item['qty'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);
            if ($qty <= 0) {
                throw new \InvalidArgumentException('Количество должно быть больше нуля.');
            }
            if ($qty > 100000) {
                throw new \InvalidArgumentException('Слишком большое количество.');
            }
            if ($productId <= 0) {
                throw new \InvalidArgumentException('Выберите товар для каждой строки.');
            }
            $normalized[] = ['product_id' => $productId, 'qty' => $qty];
        }

        $allowNegative = Settings::get('allow_negative_stock', '1') === '1';
        $shift = Shift::ensureOpen($userId);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $ids = [];
            foreach ($normalized as $item) {
                $product = Database::fetch('SELECT * FROM products WHERE id = ? FOR UPDATE', [$item['product_id']]);
                if (!$product) {
                    throw new \RuntimeException('Товар не найден.');
                }
                if ((int) $product['active'] !== 1) {
                    throw new \RuntimeException('Товар «' . $product['name'] . '» снят с продажи.');
                }
                $stock = (int) $product['stock_qty'];
                if (!$allowNegative && $stock < $item['qty']) {
                    throw new \RuntimeException('На складе «' . $product['name'] . '» только ' . $stock . ' ' . $product['unit'] . '.');
                }

                $cost = (int) $product['purchase_price_kop'];
                $total = $cost * $item['qty'];
                $after = $stock - $item['qty'];

                Database::query('UPDATE products SET stock_qty = ?, updated_at = ? WHERE id = ?', [$after, now(), $item['product_id']]);

                Database::query(
                    'INSERT INTO writeoffs (product_id, qty, unit_cost_kop, total_kop, employee_name, reason, comment, user_id, shift_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $item['product_id'],
                        $item['qty'],
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
                $ids[] = (int) $pdo->lastInsertId();

                Database::query(
                    'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                     VALUES (?, "writeoff", ?, ?, ?, ?, ?, ?)',
                    [
                        $item['product_id'],
                        -$item['qty'],
                        $after,
                        $cost,
                        'Списание: ' . $reason . ($employeeName !== '' ? ' — ' . $employeeName : ''),
                        $userId,
                        now(),
                    ]
                );
            }
            $pdo->commit();
            return $ids;
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
