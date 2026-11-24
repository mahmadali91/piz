<?php
declare(strict_types=1);

namespace PIZ\Models;

use PIZ\Database;

final class Supplier
{
    public const PAYMENT_METHODS = ['cash', 'card', 'transfer'];

    public static function all(bool $includeInactive = false): array
    {
        $where = $includeInactive ? '1 = 1' : 's.active = 1';
        return Database::fetchAll(
            "SELECT s.*,
                    COUNT(i.id) AS invoice_count,
                    COALESCE(SUM(i.total_kop - i.paid_kop), 0) AS debt_kop
             FROM suppliers s
             LEFT JOIN supplier_invoices i ON i.supplier_id = s.id AND i.status <> 'canceled'
             WHERE {$where}
             GROUP BY s.id
             ORDER BY s.active DESC, s.name ASC"
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM suppliers WHERE id = ?', [$id]);
    }

    public static function save(array $data, ?int $id = null): int
    {
        $payload = [
            $data['name'] ?? '',
            $data['contact'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['inn'] ?? null,
            $data['note'] ?? null,
            !empty($data['active']) ? 1 : 0,
            now(),
        ];
        if ($id) {
            $payload[] = now();
            $payload[] = $id;
            Database::query(
                'UPDATE suppliers SET name=?, contact=?, phone=?, email=?, address=?, inn=?, note=?, active=?, updated_at=? WHERE id=?',
                [$payload[0], $payload[1], $payload[2], $payload[3], $payload[4], $payload[5], $payload[6], $payload[7], $payload[9], $payload[10]]
            );
            return $id;
        }
        Database::query(
            'INSERT INTO suppliers (name, contact, phone, email, address, inn, note, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$payload[0], $payload[1], $payload[2], $payload[3], $payload[4], $payload[5], $payload[6], $payload[7], now(), now()]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function invoices(int $supplierId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return Database::fetchAll(
            "SELECT i.*, (i.total_kop - i.paid_kop) AS debt_kop
             FROM supplier_invoices i
             WHERE i.supplier_id = ?
             ORDER BY i.invoice_date DESC, i.id DESC
             LIMIT {$limit}",
            [$supplierId]
        );
    }

    public static function allInvoices(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return Database::fetchAll(
            "SELECT i.*, s.name AS supplier_name, (i.total_kop - i.paid_kop) AS debt_kop
             FROM supplier_invoices i
             LEFT JOIN suppliers s ON s.id = i.supplier_id
             ORDER BY i.invoice_date DESC, i.id DESC
             LIMIT {$limit}"
        );
    }

    public static function invoice(int $id): ?array
    {
        return Database::fetch(
            "SELECT i.*, s.name AS supplier_name, s.contact AS supplier_contact,
                    s.phone AS supplier_phone, s.inn AS supplier_inn,
                    (i.total_kop - i.paid_kop) AS debt_kop
             FROM supplier_invoices i
             LEFT JOIN suppliers s ON s.id = i.supplier_id
             WHERE i.id = ?",
            [$id]
        );
    }

    public static function invoiceItems(int $invoiceId): array
    {
        return Database::fetchAll(
            'SELECT ii.*, p.unit, p.sku
             FROM supplier_invoice_items ii
             LEFT JOIN products p ON p.id = ii.product_id
             WHERE ii.invoice_id = ?
             ORDER BY ii.id ASC',
            [$invoiceId]
        );
    }

    public static function payments(int $invoiceId): array
    {
        return Database::fetchAll(
            'SELECT sp.*, u.name AS user_name
             FROM supplier_payments sp
             LEFT JOIN users u ON u.id = sp.user_id
             WHERE sp.invoice_id = ?
             ORDER BY sp.paid_at ASC, sp.id ASC',
            [$invoiceId]
        );
    }

    public static function createInvoice(array $data, array $items, int $userId): int
    {
        if ($items === []) {
            throw new \InvalidArgumentException('Добавьте хотя бы одну позицию в накладную.');
        }
        $total = 0;
        $normalized = [];
        foreach ($items as $item) {
            $qty = (int) ($item['qty'] ?? 0);
            $cost = (int) ($item['unit_cost_kop'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);
            if ($qty <= 0 || $productId <= 0 || $cost < 0) {
                throw new \InvalidArgumentException('Проверьте количество и цену в накладной.');
            }
            $product = Product::find($productId);
            if (!$product) {
                throw new \InvalidArgumentException('Товар из накладной не найден.');
            }
            $sum = $qty * $cost;
            $total += $sum;
            $normalized[] = [
                'product_id' => $productId,
                'name' => $product['name'],
                'qty' => $qty,
                'unit_cost_kop' => $cost,
                'total_kop' => $sum,
            ];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::query(
                'INSERT INTO supplier_invoices
                    (supplier_id, number, invoice_date, due_date, total_kop, paid_kop, status, comment, user_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 0, "open", ?, ?, ?, ?)',
                [
                    (int) $data['supplier_id'],
                    $data['number'],
                    $data['invoice_date'],
                    $data['due_date'] ?: null,
                    $total,
                    $data['comment'] ?: null,
                    $userId,
                    now(),
                    now(),
                ]
            );
            $invoiceId = (int) $pdo->lastInsertId();
            foreach ($normalized as $item) {
                Database::query(
                    'INSERT INTO supplier_invoice_items (invoice_id, product_id, name, qty, unit_cost_kop, total_kop)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$invoiceId, $item['product_id'], $item['name'], $item['qty'], $item['unit_cost_kop'], $item['total_kop']]
                );
            }
            $pdo->commit();
            return $invoiceId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function addPayment(int $invoiceId, int $amountKop, string $method, string $comment, int $userId): void
    {
        if ($amountKop <= 0) {
            throw new \InvalidArgumentException('Сумма платежа должна быть больше нуля.');
        }
        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            $method = 'cash';
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $invoice = Database::fetch('SELECT * FROM supplier_invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$invoice) {
                throw new \RuntimeException('Накладная не найдена.');
            }
            $remaining = (int) $invoice['total_kop'] - (int) $invoice['paid_kop'];
            if ($amountKop > $remaining) {
                throw new \InvalidArgumentException('Платёж больше остатка по накладной.');
            }
            Database::query(
                'INSERT INTO supplier_payments (supplier_id, invoice_id, amount_kop, method, comment, user_id, paid_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $invoice['supplier_id'], $invoiceId, $amountKop, $method, $comment ?: null, $userId, now(), now()]
            );
            $paid = (int) $invoice['paid_kop'] + $amountKop;
            $status = $paid >= (int) $invoice['total_kop'] ? 'paid' : 'partial';
            Database::query('UPDATE supplier_invoices SET paid_kop = ?, status = ?, updated_at = ? WHERE id = ?', [$paid, $status, now(), $invoiceId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function debtTotal(): int
    {
        return (int) Database::fetchValue("SELECT COALESCE(SUM(total_kop - paid_kop), 0) FROM supplier_invoices WHERE status <> 'canceled'");
    }
}
