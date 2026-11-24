<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use PIZ\Database;

date_default_timezone_set('Europe/Moscow');
$purge = in_array('--purge', $argv, true);
$days = 90;
foreach ($argv as $arg) {
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) {
        $days = max(1, min(180, (int) $m[1]));
    }
}

$pdo = Database::pdo();

function demoCount(string $table, string $where, array $params = []): int
{
    return (int) Database::fetchValue("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
}

if ($purge) {
    $pdo->beginTransaction();
    try {
        $saleIds = array_map('intval', array_column(Database::fetchAll("SELECT id FROM sales WHERE number LIKE 'DEMO-%'"), 'id'));
        if ($saleIds !== []) {
            $in = implode(',', array_fill(0, count($saleIds), '?'));
            Database::query("DELETE FROM sale_items WHERE sale_id IN ({$in})", $saleIds);
            Database::query("DELETE FROM stock_movements WHERE sale_id IN ({$in})", $saleIds);
            Database::query("DELETE FROM sales WHERE id IN ({$in})", $saleIds);
        }
        Database::query("DELETE FROM stock_movements WHERE reason LIKE '[DEMO]%'");
        Database::query("DELETE FROM product_receipts WHERE comment LIKE '[DEMO]%'");
        Database::query("DELETE FROM writeoffs WHERE comment LIKE '[DEMO]%'");
        Database::query("DELETE FROM product_price_history WHERE comment LIKE '[DEMO]%'");

        $shiftIds = array_map('intval', array_column(Database::fetchAll("SELECT id FROM shifts WHERE note LIKE '[DEMO]%'"), 'id'));
        if ($shiftIds !== []) {
            $in = implode(',', array_fill(0, count($shiftIds), '?'));
            Database::query("DELETE FROM shift_stock WHERE shift_id IN ({$in})", $shiftIds);
            Database::query("DELETE FROM shifts WHERE id IN ({$in})", $shiftIds);
        }

        $supplierIds = array_map('intval', array_column(Database::fetchAll("SELECT id FROM suppliers WHERE name LIKE '[DEMO]%'"), 'id'));
        if ($supplierIds !== []) {
            $in = implode(',', array_fill(0, count($supplierIds), '?'));
            $invoiceIds = array_map('intval', array_column(Database::fetchAll("SELECT id FROM supplier_invoices WHERE supplier_id IN ({$in})", $supplierIds), 'id'));
            if ($invoiceIds !== []) {
                $iin = implode(',', array_fill(0, count($invoiceIds), '?'));
                Database::query("DELETE FROM supplier_payments WHERE invoice_id IN ({$iin})", $invoiceIds);
                Database::query("DELETE FROM supplier_invoice_items WHERE invoice_id IN ({$iin})", $invoiceIds);
                Database::query("DELETE FROM supplier_invoices WHERE id IN ({$iin})", $invoiceIds);
            }
            Database::query("DELETE FROM suppliers WHERE id IN ({$in})", $supplierIds);
        }

        $inventoryIds = array_map('intval', array_column(Database::fetchAll("SELECT id FROM inventory_sessions WHERE note LIKE '[DEMO]%'"), 'id'));
        if ($inventoryIds !== []) {
            $in = implode(',', array_fill(0, count($inventoryIds), '?'));
            Database::query("DELETE FROM inventory_lines WHERE session_id IN ({$in})", $inventoryIds);
            Database::query("DELETE FROM inventory_sessions WHERE id IN ({$in})", $inventoryIds);
        }

        Database::query("DELETE FROM audit_log WHERE action LIKE 'demo.%'");
        $pdo->commit();
        echo "Демо-данные удалены.\n";
        exit(0);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, 'Ошибка удаления: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

if (demoCount('sales', "number LIKE 'DEMO-%'") > 0) {
    fwrite(STDERR, "Демо-данные уже есть. Сначала удалите их: php8.2 tools/seed_demo.php --purge\n");
    exit(1);
}

$products = Database::fetchAll('SELECT * FROM products WHERE active = 1 ORDER BY id');
if ($products === []) {
    fwrite(STDERR, "Нет активных товаров.\n");
    exit(1);
}
$admin = Database::fetch('SELECT id, name FROM users WHERE role = "admin" AND active = 1 ORDER BY id LIMIT 1');
if (!$admin) {
    fwrite(STDERR, "Нет активного администратора.\n");
    exit(1);
}
$adminId = (int) $admin['id'];
$adminName = (string) $admin['name'];
$stockUnits = 0;
$stockValue = 0;
$productById = [];
foreach ($products as $product) {
    $productById[(int) $product['id']] = $product;
    $stockUnits += (int) $product['stock_qty'];
    $stockValue += (int) $product['stock_qty'] * (int) $product['purchase_price_kop'];
}

$start = new DateTimeImmutable('today -' . $days . ' days');
$end = new DateTimeImmutable('yesterday');
$reasonKeys = array_keys(\PIZ\Models\Writeoff::REASONS);
mt_srand(20260826);

$pdo->beginTransaction();
try {
    $totalSales = 0;
    $totalReceipts = 0;
    $totalWriteoffs = 0;
    $totalShiftRows = 0;

    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $day = $date->format('Y-m-d');
        $dayNumber = $date->format('N');
        $weekend = (int) $dayNumber >= 6;
        $salesTarget = 5 + random_int(0, 4) + ($weekend ? 2 : 0);
        $openedAt = $day . ' 08:30:00';
        $closedAt = $day . ' 21:30:00';
        Database::query(
            'INSERT INTO shifts (opened_by, opened_at, closed_by, closed_at, status, note, stock_units, stock_value_kop)
             VALUES (?, ?, ?, ?, "closed", ?, ?, ?)',
            [$adminId, $openedAt, $adminId, $closedAt, '[DEMO] Смена за ' . $date->format('d.m.Y'), $stockUnits, $stockValue]
        );
        $shiftId = (int) $pdo->lastInsertId();
        foreach ($products as $product) {
            Database::query(
                'INSERT INTO shift_stock (shift_id, product_id, name, sku, qty, unit_cost_kop, sale_price_kop, stock_value_kop)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $shiftId,
                    (int) $product['id'],
                    $product['name'],
                    $product['sku'],
                    (int) $product['stock_qty'],
                    (int) $product['purchase_price_kop'],
                    (int) $product['sale_price_kop'],
                    (int) $product['stock_qty'] * (int) $product['purchase_price_kop'],
                ]
            );
            $totalShiftRows++;
        }

        $shiftCount = 0;
        $shiftRevenue = 0;
        $shiftCost = 0;
        $shiftExpense = 0;
        for ($n = 1; $n <= $salesTarget; $n++) {
            $itemCount = random_int(1, 3);
            $chosen = [];
            while (count($chosen) < $itemCount) {
                $chosen[random_int(0, count($products) - 1)] = true;
            }
            $items = [];
            $total = 0;
            $cost = 0;
            foreach (array_keys($chosen) as $index) {
                $product = $products[$index];
                $qty = random_int(1, 2);
                $price = (int) $product['sale_price_kop'];
                $unitCost = (int) $product['purchase_price_kop'];
                $sum = $price * $qty;
                $items[] = ['product' => $product, 'qty' => $qty, 'price' => $price, 'cost' => $unitCost, 'total' => $sum];
                $total += $sum;
                $cost += $unitCost * $qty;
            }
            $discount = random_int(1, 10) <= 2 ? (int) round($total * random_int(3, 8) / 100) : 0;
            $payable = max(0, $total - $discount);
            $payment = ['cash', 'cash', 'cash', 'card', 'card', 'transfer'][random_int(0, 5)];
            $received = $payable;
            $change = 0;
            if ($payment === 'cash') {
                $received = (int) (ceil($payable / 10000) * 10000);
                if ($received < $payable) {
                    $received = $payable;
                }
                $change = $received - $payable;
            }
            $number = 'DEMO-' . $date->format('ymd') . '-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $createdAt = $day . ' ' . str_pad((string) random_int(9, 20), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) random_int(0, 59), 2, '0', STR_PAD_LEFT) . ':00';
            $customer = random_int(1, 5) === 1 ? ['Али', 'Гость', 'Постоянный клиент'][random_int(0, 2)] : null;
            Database::query(
                'INSERT INTO sales (number, user_id, total_kop, cost_kop, discount_kop, payment_method, received_kop, change_kop, customer, comment, status, shift_id, client_token, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "completed", ?, ?, ?)',
                [$number, $adminId, $payable, $cost, $discount, $payment, $received, $change, $customer, '[DEMO]', $shiftId, 'demo-' . $day . '-' . $n, $createdAt]
            );
            $saleId = (int) $pdo->lastInsertId();
            foreach ($items as $item) {
                Database::query(
                    'INSERT INTO sale_items (sale_id, product_id, name, qty, price_kop, cost_kop, total_kop) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$saleId, (int) $item['product']['id'], $item['product']['name'], $item['qty'], $item['price'], $item['cost'], $item['total']]
                );
                Database::query(
                    'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, sale_id, user_id, created_at)
                     VALUES (?, "sale", ?, ?, ?, ?, ?, ?, ?)',
                    [(int) $item['product']['id'], -$item['qty'], (int) $item['product']['stock_qty'], (int) $item['cost'], '[DEMO] Продажа ' . $number, $saleId, $adminId, $createdAt]
                );
            }
            $shiftCount++;
            $shiftRevenue += $payable;
            $shiftCost += $cost;
            $totalSales++;
        }

        if (random_int(1, 10) <= 2) {
            $product = $products[random_int(0, count($products) - 1)];
            $qty = 1;
            $cost = (int) $product['purchase_price_kop'];
            $reason = $reasonKeys[random_int(0, count($reasonKeys) - 1)];
            $createdAt = $day . ' ' . str_pad((string) random_int(12, 20), 2, '0', STR_PAD_LEFT) . ':15:00';
            Database::query(
                'INSERT INTO writeoffs (product_id, qty, unit_cost_kop, total_kop, employee_name, reason, comment, user_id, shift_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $product['id'], $qty, $cost, $cost, random_int(0, 1) ? 'Кассир 1' : $adminName, $reason, '[DEMO] Расход смены', $adminId, $shiftId, $createdAt]
            );
            Database::query(
                'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                 VALUES (?, "writeoff", ?, ?, ?, ?, ?, ?)',
                [(int) $product['id'], -$qty, (int) $product['stock_qty'], $cost, '[DEMO] Списание: ' . $reason, $adminId, $createdAt]
            );
            $shiftExpense += $cost;
            $totalWriteoffs++;
        }

        Database::query(
            'UPDATE shifts SET sales_count = ?, revenue_kop = ?, cost_kop = ?, expense_kop = ?, profit_kop = ? WHERE id = ?',
            [$shiftCount, $shiftRevenue, $shiftCost, $shiftExpense, $shiftRevenue - $shiftCost - $shiftExpense, $shiftId]
        );
    }

    // Демо-приходы: история партий, без изменения текущих остатков.
    for ($i = 0; $i < 22; $i++) {
        $date = $start->modify('+' . ($i * 4) . ' days')->format('Y-m-d');
        $count = random_int(2, 4);
        for ($j = 0; $j < $count; $j++) {
            $product = $products[random_int(0, count($products) - 1)];
            $qty = random_int(10, 40);
            $cost = (int) $product['purchase_price_kop'];
            $at = $date . ' ' . str_pad((string) random_int(9, 11), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) random_int(0, 59), 2, '0', STR_PAD_LEFT) . ':00';
            Database::query(
                'INSERT INTO product_receipts (product_id, qty, unit_cost_kop, total_kop, comment, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [(int) $product['id'], $qty, $cost, $qty * $cost, '[DEMO] Поставка', $adminId, $at]
            );
            Database::query(
                'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                 VALUES (?, "in", ?, ?, ?, ?, ?, ?)',
                [(int) $product['id'], $qty, (int) $product['stock_qty'], $cost, '[DEMO] Приход товара', $adminId, $at]
            );
            $totalReceipts++;
        }
    }

    // Демо-поставщик, накладные и оплаты.
    Database::query(
        'INSERT INTO suppliers (name, contact, phone, email, address, inn, note, active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
        ['[DEMO] Поставщик продуктов', 'Иван', '+7 900 000-00-00', 'demo-supplier@example.com', 'Москва', '0000000000', '[DEMO] Демонстрационный поставщик', now(), now()]
    );
    $supplierId = (int) $pdo->lastInsertId();
    for ($i = 1; $i <= 3; $i++) {
        $invoiceDate = $start->modify('+' . (($i - 1) * 30 + 5) . ' days')->format('Y-m-d');
        $number = 'DEMO-INV-' . $i;
        $items = [];
        $total = 0;
        $itemCount = random_int(4, 7);
        $used = [];
        while (count($items) < $itemCount) {
            $product = $products[random_int(0, count($products) - 1)];
            if (isset($used[(int) $product['id']])) {
                continue;
            }
            $used[(int) $product['id']] = true;
            $qty = random_int(10, 35);
            $cost = (int) $product['purchase_price_kop'];
            $items[] = ['product' => $product, 'qty' => $qty, 'cost' => $cost, 'total' => $qty * $cost];
            $total += $qty * $cost;
        }
        $paid = $i === 3 ? 0 : ($i === 2 ? (int) round($total / 2) : $total);
        $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'open');
        Database::query(
            'INSERT INTO supplier_invoices (supplier_id, number, invoice_date, due_date, total_kop, paid_kop, status, comment, user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$supplierId, $number, $invoiceDate, $invoiceDate, $total, $paid, $status, '[DEMO] Накладная', $adminId, now(), now()]
        );
        $invoiceId = (int) $pdo->lastInsertId();
        foreach ($items as $item) {
            Database::query(
                'INSERT INTO supplier_invoice_items (invoice_id, product_id, name, qty, unit_cost_kop, total_kop) VALUES (?, ?, ?, ?, ?, ?)',
                [$invoiceId, (int) $item['product']['id'], $item['product']['name'], $item['qty'], $item['cost'], $item['total']]
            );
        }
        if ($paid > 0) {
            Database::query(
                'INSERT INTO supplier_payments (supplier_id, invoice_id, amount_kop, method, comment, user_id, paid_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$supplierId, $invoiceId, $paid, 'transfer', '[DEMO] Оплата накладной', $adminId, $invoiceDate . ' 12:00:00', now()]
            );
        }
    }

    // Демо-инвентаризации.
    for ($i = 0; $i < 3; $i++) {
        $sessionDate = $start->modify('+' . (($i + 1) * 30 - 2) . ' days')->format('Y-m-d');
        $number = 'DEMO-INV-' . $sessionDate;
        Database::query(
            'INSERT INTO inventory_sessions (number, warehouse, status, started_by, started_at, completed_by, completed_at, note, total_products, total_calculated, total_actual, total_diff, total_value_kop)
             VALUES (?, ?, "completed", ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)',
            [$number, 'Основной склад', $adminId, $sessionDate . ' 08:00:00', $adminId, $sessionDate . ' 08:40:00', '[DEMO] Инвентаризация', count($products), $stockUnits, $stockUnits]
        );
        $sessionId = (int) $pdo->lastInsertId();
        foreach ($products as $product) {
            $actual = (int) $product['stock_qty'];
            Database::query(
                'INSERT INTO inventory_lines (session_id, product_id, name, sku, unit, calculated_qty, actual_qty, diff_qty, cost_kop, diff_value_kop)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 0)',
                [$sessionId, (int) $product['id'], $product['name'], $product['sku'], $product['unit'], $actual, $actual, (int) $product['purchase_price_kop']]
            );
        }
    }

    // Демо-история цен.
    for ($i = 0; $i < 3; $i++) {
        $product = $products[$i];
        $oldPurchase = max(0, (int) $product['purchase_price_kop'] - 500);
        $oldSale = max(0, (int) $product['sale_price_kop'] - 1000);
        Database::query(
            'INSERT INTO product_price_history (product_id, old_purchase_kop, new_purchase_kop, old_sale_kop, new_sale_kop, source, comment, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, "manual", ?, ?, ?)',
            [(int) $product['id'], $oldPurchase, (int) $product['purchase_price_kop'], $oldSale, (int) $product['sale_price_kop'], '[DEMO] Плановое изменение', $adminId, $start->modify('+' . (10 + $i * 20) . ' days')->format('Y-m-d') . ' 10:00:00']
        );
    }

    Database::query(
        'INSERT INTO audit_log (user_id, action, details, ip, created_at) VALUES (?, "demo.seed", ?, ?, ?)',
        [$adminId, json_encode(['days' => $days, 'sales' => $totalSales, 'receipts' => $totalReceipts, 'writeoffs' => $totalWriteoffs], JSON_UNESCAPED_UNICODE), '127.0.0.1', now()]
    );

    $pdo->commit();
    echo "Демо-данные добавлены за {$days} дней.\n";
    echo "Продаж: {$totalSales}, приходов: {$totalReceipts}, списаний: {$totalWriteoffs}, смен: " . $days . ".\n";
    echo "Поставщик: 3 накладные, инвентаризаций: 3, история цен: 3.\n";
    echo "Удалить: php8.2 tools/seed_demo.php --purge\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . "\n");
    exit(1);
}
