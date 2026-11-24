<?php
declare(strict_types=1);

namespace PIZ\Models;

use PIZ\Database;

final class Receipt
{
    public static function list(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $where = '1 = 1';
        $params = [];
        if (!empty($filters['product_id'])) {
            $where .= ' AND r.product_id = ?';
            $params[] = (int) $filters['product_id'];
        }
        return Database::fetchAll(
            "SELECT r.*, p.name AS product_name, p.unit, u.name AS user_name
             FROM product_receipts r
             LEFT JOIN products p ON p.id = r.product_id
             LEFT JOIN users u ON u.id = r.user_id
             WHERE {$where}
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function count(array $filters = []): int
    {
        $where = '1 = 1';
        $params = [];
        if (!empty($filters['product_id'])) {
            $where .= ' AND product_id = ?';
            $params[] = (int) $filters['product_id'];
        }
        return (int) Database::fetchValue("SELECT COUNT(*) FROM product_receipts WHERE {$where}", $params);
    }

    public static function totals(): array
    {
        $row = Database::fetch(
            'SELECT COALESCE(SUM(qty), 0) AS qty, COALESCE(SUM(total_kop), 0) AS total FROM product_receipts'
        );
        return ['qty' => (int) ($row['qty'] ?? 0), 'total' => (int) ($row['total'] ?? 0)];
    }
}
