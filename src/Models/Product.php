<?php
declare(strict_types=1);

namespace PIZ\Models;

use PIZ\Database;
use PIZ\Settings;

final class Product
{
    public static function all(array $filters = []): array
    {
        $sql = 'SELECT * FROM products WHERE 1 = 1';
        $params = [];

        if (empty($filters['include_archived'])) {
            $sql .= ' AND active = 1';
        } elseif (!empty($filters['only_archived'])) {
            $sql .= ' AND active = 0';
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (name LIKE ? OR sku LIKE ? OR category LIKE ?)';
            $needle = '%' . $filters['search'] . '%';
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }

        if (!empty($filters['category'])) {
            $sql .= ' AND category = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['low_only'])) {
            $sql .= ' AND stock_qty <= low_stock';
        }

        if (!empty($filters['out_only'])) {
            $sql .= ' AND stock_qty <= 0';
        }

        $sql .= ' ORDER BY active DESC, name ASC';
        return Database::fetchAll($sql, $params);
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM products WHERE id = ?', [$id]);
    }

    /** @return string[] */
    public static function categories(): array
    {
        $rows = Database::fetchAll('SELECT DISTINCT category FROM products ORDER BY category ASC');
        return array_values(array_filter(array_map(static fn ($r) => (string) $r['category'], $rows)));
    }


    public static function recordPriceHistory(
        int $id,
        int $oldPurchase,
        int $oldSale,
        int $newPurchase,
        int $newSale,
        ?int $userId,
        string $source = 'manual',
        string $comment = ''
    ): void {
        if ($oldPurchase === $newPurchase && $oldSale === $newSale) {
            return;
        }
        Database::query(
            'INSERT INTO product_price_history
                (product_id, old_purchase_kop, new_purchase_kop, old_sale_kop, new_sale_kop, source, comment, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                max(0, $oldPurchase),
                max(0, $newPurchase),
                max(0, $oldSale),
                max(0, $newSale),
                mb_substr($source, 0, 24),
                $comment !== '' ? mb_substr($comment, 0, 255) : null,
                $userId,
                now(),
            ]
        );
    }

    public static function priceHistory(int $id, int $limit = 15): array
    {
        $limit = max(1, min(50, $limit));
        return Database::fetchAll(
            "SELECT h.*, u.name AS user_name
             FROM product_price_history h
             LEFT JOIN users u ON u.id = h.user_id
             WHERE h.product_id = ?
             ORDER BY h.created_at DESC, h.id DESC
             LIMIT {$limit}",
            [$id]
        );
    }

    public static function formattedPriceHistory(int $id, int $limit = 15): array
    {
        static $cache = [];
        $cacheKey = $id . ':' . $limit;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }
        $cache[$cacheKey] = array_map(static fn (array $row): array => [
            'date' => dt_human($row['created_at']),
            'user' => $row['user_name'] ?? 'Система',
            'source' => price_source_label((string) $row['source']),
            'comment' => (string) ($row['comment'] ?? ''),
            'purchase_changed' => (int) $row['old_purchase_kop'] !== (int) $row['new_purchase_kop'],
            'sale_changed' => (int) $row['old_sale_kop'] !== (int) $row['new_sale_kop'],
            'old_purchase' => money((int) $row['old_purchase_kop']),
            'new_purchase' => money((int) $row['new_purchase_kop']),
            'old_sale' => money((int) $row['old_sale_kop']),
            'new_sale' => money((int) $row['new_sale_kop']),
        ], self::priceHistory($id, $limit));
        return $cache[$cacheKey];
    }

    public static function create(array $data, ?int $userId): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::query(
                'INSERT INTO products (sku, name, category, purchase_price_kop, sale_price_kop, stock_qty, unit, low_stock, photo, active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
                [
                    $data['sku'] ?: null,
                    $data['name'],
                    $data['category'],
                    $data['purchase_price_kop'],
                    $data['sale_price_kop'],
                    $data['stock_qty'],
                    $data['unit'],
                    $data['low_stock'],
                    $data['photo'] ?? null,
                    now(),
                    now(),
                ]
            );
            $id = (int) $pdo->lastInsertId();

            if (empty($data['sku'])) {
                Database::query('UPDATE products SET sku = ? WHERE id = ?', ['P' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);
            }

            if ($data['stock_qty'] > 0) {
                Database::query(
                    'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                     VALUES (?, "initial", ?, ?, ?, ?, ?, ?)',
                    [$id, $data['stock_qty'], $data['stock_qty'], $data['purchase_price_kop'], 'Начальный остаток', $userId, now()]
                );
                Database::query(
                    'INSERT INTO product_receipts (product_id, qty, unit_cost_kop, total_kop, comment, user_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$id, $data['stock_qty'], $data['purchase_price_kop'], $data['stock_qty'] * $data['purchase_price_kop'], 'Начальный остаток', $userId, now()]
                );
            }

            self::recordPriceHistory(
                $id,
                0,
                0,
                (int) $data['purchase_price_kop'],
                (int) $data['sale_price_kop'],
                $userId,
                'create',
                'Создание товара'
            );

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(
        int $id,
        array $data,
        ?int $userId = null,
        string $source = 'manual',
        string $comment = ''
    ): void {
        $old = Database::fetch('SELECT purchase_price_kop, sale_price_kop FROM products WHERE id = ?', [$id]);
        Database::query(
            'UPDATE products SET sku = ?, name = ?, category = ?, purchase_price_kop = ?, sale_price_kop = ?, unit = ?, low_stock = ?, photo = ?, updated_at = ? WHERE id = ?',
            [
                $data['sku'] ?: null,
                $data['name'],
                $data['category'],
                $data['purchase_price_kop'],
                $data['sale_price_kop'],
                $data['unit'],
                $data['low_stock'],
                $data['photo'] ?? null,
                now(),
                $id,
            ]
        );
        if ($old) {
            self::recordPriceHistory(
                $id,
                (int) $old['purchase_price_kop'],
                (int) $old['sale_price_kop'],
                (int) $data['purchase_price_kop'],
                (int) $data['sale_price_kop'],
                $userId,
                $source,
                $comment
            );
        }
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::query('UPDATE products SET active = ?, updated_at = ? WHERE id = ?', [$active ? 1 : 0, now(), $id]);
    }

    /**
     * Приход товара: увеличивает остаток, сохраняет партию с её себестоимостью
     * и пересчитывает средневзвешенную закупочную цену товара.
     */
    public static function addStock(int $id, int $qty, ?int $unitCostKop, string $reason, ?int $userId, string $comment = ''): int
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Количество должно быть больше нуля.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $product = Database::fetch('SELECT * FROM products WHERE id = ? FOR UPDATE', [$id]);
            if (!$product) {
                throw new \RuntimeException('Товар не найден.');
            }

            $oldQty = (int) $product['stock_qty'];
            $oldCost = (int) $product['purchase_price_kop'];
            $receiptCost = $unitCostKop !== null ? max(0, $unitCostKop) : $oldCost;
            $after = $oldQty + $qty;

            if ($oldQty > 0 && $after > 0) {
                $newAverage = (int) round((($oldQty * $oldCost) + ($qty * $receiptCost)) / $after);
            } else {
                $newAverage = $receiptCost > 0 ? $receiptCost : $oldCost;
            }

            Database::query(
                'UPDATE products SET stock_qty = ?, purchase_price_kop = ?, updated_at = ? WHERE id = ?',
                [$after, $newAverage, now(), $id]
            );

            Database::query(
                'INSERT INTO product_receipts (product_id, qty, unit_cost_kop, total_kop, comment, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $qty, $receiptCost, $qty * $receiptCost, $comment !== '' ? $comment : ($reason !== '' ? $reason : 'Приход товара'), $userId, now()]
            );

            Database::query(
                'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                 VALUES (?, "in", ?, ?, ?, ?, ?, ?)',
                [$id, $qty, $after, $receiptCost, $reason !== '' ? $reason : 'Приход товара', $userId, now()]
            );

            self::recordPriceHistory(
                $id,
                $oldCost,
                (int) $product['sale_price_kop'],
                $newAverage,
                (int) $product['sale_price_kop'],
                $userId,
                'receipt',
                'Приход: ' . $qty . ' по ' . kop_to_input($receiptCost) . ' ₽'
            );

            $pdo->commit();
            return $newAverage;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Инвентаризация: устанавливает точный остаток.
     */
    public static function setStock(int $id, int $newQty, string $reason, ?int $userId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $product = Database::fetch('SELECT * FROM products WHERE id = ? FOR UPDATE', [$id]);
            if (!$product) {
                throw new \RuntimeException('Товар не найден.');
            }
            $delta = $newQty - (int) $product['stock_qty'];
            if ($delta === 0) {
                $pdo->commit();
                return;
            }
            Database::query('UPDATE products SET stock_qty = ?, updated_at = ? WHERE id = ?', [$newQty, now(), $id]);
            Database::query(
                'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                 VALUES (?, "adjust", ?, ?, ?, ?, ?, ?)',
                [$id, $delta, $newQty, (int) $product['purchase_price_kop'], $reason !== '' ? $reason : 'Инвентаризация', $userId, now()]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updatePrices(
        int $id,
        int $purchaseKop,
        int $saleKop,
        ?int $userId = null,
        string $source = 'manual',
        string $comment = ''
    ): void {
        $old = Database::fetch('SELECT purchase_price_kop, sale_price_kop FROM products WHERE id = ?', [$id]);
        Database::query(
            'UPDATE products SET purchase_price_kop = ?, sale_price_kop = ?, updated_at = ? WHERE id = ?',
            [max(0, $purchaseKop), max(0, $saleKop), now(), $id]
        );
        if ($old) {
            self::recordPriceHistory(
                $id,
                (int) $old['purchase_price_kop'],
                (int) $old['sale_price_kop'],
                max(0, $purchaseKop),
                max(0, $saleKop),
                $userId,
                $source,
                $comment
            );
        }
    }

    public static function stats(): array
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(stock_qty), 0) AS units,
                    COALESCE(SUM(CAST(stock_qty AS SIGNED) * CAST(purchase_price_kop AS SIGNED)), 0) AS cost_value,
                    COALESCE(SUM(CAST(stock_qty AS SIGNED) * CAST(sale_price_kop AS SIGNED)), 0) AS retail_value,
                    COALESCE(SUM(CASE WHEN stock_qty <= low_stock THEN 1 ELSE 0 END), 0) AS low_count,
                    COALESCE(SUM(CASE WHEN stock_qty <= 0 THEN 1 ELSE 0 END), 0) AS out_count
             FROM products WHERE active = 1'
        );
        return [
            'total' => (int) ($row['total'] ?? 0),
            'units' => (int) ($row['units'] ?? 0),
            'cost_value' => (int) ($row['cost_value'] ?? 0),
            'retail_value' => (int) ($row['retail_value'] ?? 0),
            'low_count' => (int) ($row['low_count'] ?? 0),
            'out_count' => (int) ($row['out_count'] ?? 0),
        ];
    }

    public static function lowStock(int $limit = 8): array
    {
        return Database::fetchAll(
            'SELECT * FROM products WHERE active = 1 AND stock_qty <= low_stock ORDER BY stock_qty ASC, name ASC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<string, array> */
    public static function byNameMap(): array
    {
        $map = [];
        foreach (Database::fetchAll('SELECT * FROM products') as $product) {
            $map[self::normalizeName((string) $product['name'])] = $product;
        }
        return $map;
    }

    public static function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        return $name;
    }

    public static function defaultLowStock(): int
    {
        return max(0, (int) Settings::get('low_stock_default', '3'));
    }
}
