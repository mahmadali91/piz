<?php
declare(strict_types=1);

/**
 * Миграция базы под версию «КИОСК ДОНЕР»:
 * приходы с партиями, списания, смены и отрицательные остатки.
 *
 * Запуск: php8.2 tools/migrate.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';

use PIZ\Database;
use PIZ\Install;
use PIZ\Settings;

if (!Database::isConnected()) {
    fwrite(STDERR, "Нет подключения к базе. Проверьте config/config.php\n");
    exit(1);
}

echo "== Схема ==\n";
Install::migrate();

if (!Database::columnExists('sales', 'shift_id')) {
    Database::pdo()->exec('ALTER TABLE sales ADD COLUMN shift_id INT UNSIGNED NULL AFTER status, ADD KEY idx_sales_shift (shift_id)');
    echo "Добавлено поле sales.shift_id\n";
}

Database::query("INSERT IGNORE INTO settings (skey, svalue) VALUES ('allow_negative_stock', '1')");
Database::query("INSERT IGNORE INTO settings (skey, svalue) VALUES ('store_name', 'КИОСК ДОНЕР')");

$storeName = (string) (Settings::get('store_name', 'КИОСК ДОНЕР'));
if (in_array($storeName, ['Киоск PIZ', 'КИОСК PIZ', 'PIZ', 'piz'], true)) {
    Settings::set('store_name', 'КИОСК ДОНЕР');
    Settings::reload();
    echo "Название изменено на КИОСК ДОНЕР\n";
}

$receipts = (int) Database::fetchValue('SELECT COUNT(*) FROM product_receipts');
if ($receipts === 0) {
    $products = Database::fetchAll('SELECT id, stock_qty, purchase_price_kop FROM products WHERE stock_qty <> 0');
    $pdo = Database::pdo();
    $pdo->beginTransaction();
    foreach ($products as $product) {
        $qty = (int) $product['stock_qty'];
        $cost = (int) $product['purchase_price_kop'];
        Database::query(
            'INSERT INTO product_receipts (product_id, qty, unit_cost_kop, total_kop, comment, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, NULL, ?)',
            [(int) $product['id'], $qty, $cost, $qty * $cost, 'Начальный остаток (миграция)', now()]
        );
    }
    $pdo->commit();
    echo 'Создано базовых приходов: ' . count($products) . "\n";
}

$priceHistory = (int) Database::fetchValue('SELECT COUNT(*) FROM product_price_history');
if ($priceHistory === 0) {
    $products = Database::fetchAll('SELECT id, purchase_price_kop, sale_price_kop FROM products');
    $pdo = Database::pdo();
    $pdo->beginTransaction();
    foreach ($products as $product) {
        Database::query(
            'INSERT INTO product_price_history
                (product_id, old_purchase_kop, new_purchase_kop, old_sale_kop, new_sale_kop, source, comment, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, "baseline", ?, NULL, ?)',
            [
                (int) $product['id'],
                (int) $product['purchase_price_kop'],
                (int) $product['purchase_price_kop'],
                (int) $product['sale_price_kop'],
                (int) $product['sale_price_kop'],
                'Начало учёта истории цен',
                now(),
            ]
        );
    }
    $pdo->commit();
    echo 'Создано базовых записей истории цен: ' . count($products) . "\n";
}

echo "== Готово ==\n";
echo 'Товаров: ' . Database::fetchValue('SELECT COUNT(*) FROM products') . "\n";
echo 'Приходов: ' . Database::fetchValue('SELECT COUNT(*) FROM product_receipts') . "\n";
echo 'Смен: ' . Database::fetchValue('SELECT COUNT(*) FROM shifts') . "\n";
echo 'Списаний: ' . Database::fetchValue('SELECT COUNT(*) FROM writeoffs') . "\n";
