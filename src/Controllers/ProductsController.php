<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Models\Product;
use PIZ\Services\ImageUploader;
use PIZ\Services\Importer;
use PIZ\View;

final class ProductsController
{
    public function index(): void
    {
        Auth::requirePermission('products.view');

        $filters = [
            'search' => trim((string) ($_GET['q'] ?? '')),
            'category' => trim((string) ($_GET['category'] ?? '')),
            'low_only' => isset($_GET['low']),
            'include_archived' => isset($_GET['archived']),
        ];

        View::render('products/index', [
            'title' => 'Товары и остаток',
            'active' => 'products',
            'user' => Auth::user(),
            'products' => Product::all($filters),
            'categories' => Product::categories(),
            'filters' => $filters,
            'stats' => Product::stats(),
            'finance' => Auth::can('finance.view'),
            'canManage' => Auth::can('products.manage'),
            'canStock' => Auth::can('stock.manage'),
            'canPrices' => Auth::can('prices.manage'),
        ]);
    }

    public function save(): void
    {
        Auth::requirePermission('products.manage');

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id > 0 ? Product::find($id) : null;
        if ($id > 0 && !$existing) {
            flash('error', 'Товар не найден.');
            redirect('/products');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $sku = trim((string) ($_POST['sku'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $unit = trim((string) ($_POST['unit'] ?? ''));
        $purchaseRaw = $_POST['purchase_price'] ?? null;
        $purchase = $purchaseRaw !== null && $purchaseRaw !== '' ? parse_money((string) $purchaseRaw) : null;
        $sale = parse_money($_POST['sale_price'] ?? '');
        $lowStock = (int) ($_POST['low_stock'] ?? 3);
        $stockQty = Auth::can('stock.manage') ? (int) ($_POST['stock_qty'] ?? 0) : 0;

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            $errors[] = 'Название товара: от 2 до 160 символов.';
        }
        if ($purchase === null) {
            $purchase = $existing ? (int) $existing['purchase_price_kop'] : 0;
        }
        if (!Auth::can('prices.manage')) {
            if ($existing) {
                $purchase = (int) $existing['purchase_price_kop'];
                $sale = (int) $existing['sale_price_kop'];
            } else {
                $errors[] = 'Нет права устанавливать цены на товар.';
            }
        }
        if ($purchase < 0) {
            $errors[] = 'Закупочная цена не может быть отрицательной.';
        }
        if ($sale === null || $sale <= 0) {
            $errors[] = 'Укажите цену продажи больше нуля.';
        }
        if ($lowStock < 0) {
            $lowStock = 0;
        }
        if ($id === 0 && $stockQty < 0) {
            $stockQty = 0;
        }
        if ($category === '') {
            $category = 'Без категории';
        }
        if ($unit === '') {
            $unit = 'шт';
        }

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            redirect('/products');
        }

        $photo = $existing['photo'] ?? null;
        try {
            if (!empty($_POST['remove_photo'])) {
                ImageUploader::delete($photo);
                $photo = null;
            }
            $uploaded = ImageUploader::save($_FILES['photo'] ?? null);
            if ($uploaded !== null) {
                ImageUploader::delete($photo);
                $photo = $uploaded;
            }
        } catch (\Throwable $e) {
            flash('error', 'Фото не загружено: ' . $e->getMessage());
            redirect('/products');
        }

        $data = [
            'sku' => mb_substr($sku, 0, 32),
            'name' => mb_substr($name, 0, 160),
            'category' => mb_substr($category, 0, 80),
            'purchase_price_kop' => $purchase,
            'sale_price_kop' => $sale,
            'unit' => mb_substr($unit, 0, 16),
            'low_stock' => min(100000, $lowStock),
            'photo' => $photo,
            'stock_qty' => max(0, min(1000000, $stockQty)),
        ];

        try {
            if ($existing) {
                Product::update($id, $data, Auth::id(), 'manual');
                Audit::log('product.update', ['id' => $id, 'name' => $data['name']]);
                flash('success', 'Товар обновлён.');
            } else {
                $newId = Product::create($data, Auth::id());
                Audit::log('product.create', ['id' => $newId, 'name' => $data['name'], 'stock' => $data['stock_qty']]);
                flash('success', 'Товар добавлен.');
            }
        } catch (\Throwable $e) {
            error_log('[PIZ] product save: ' . $e->getMessage());
            flash('error', 'Не удалось сохранить товар: ' . $e->getMessage());
        }

        redirect('/products');
    }

    public function toggle(): void
    {
        Auth::requirePermission('products.manage');
        $id = (int) ($_POST['id'] ?? 0);
        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Товар не найден.');
            redirect('/products');
        }
        $active = !((int) $product['active'] === 1);
        Product::setActive($id, $active);
        Audit::log($active ? 'product.restore' : 'product.archive', ['id' => $id, 'name' => $product['name']]);
        flash('success', $active ? 'Товар возвращён в продажу.' : 'Товар перемещён в архив.');
        redirect('/products');
    }

    public function stock(): void
    {
        Auth::requirePermission('stock.manage');
        $id = (int) ($_POST['id'] ?? 0);
        $mode = (string) ($_POST['mode'] ?? 'in');
        $qty = (int) ($_POST['qty'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $unitCost = parse_money($_POST['unit_cost'] ?? '');

        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Товар не найден.');
            redirect('/products');
        }
        if ($mode !== 'in' && $mode !== 'set') {
            flash('error', 'Неизвестная операция.');
            redirect('/products');
        }

        try {
            if (!Auth::can('prices.manage')) {
                $unitCost = null;
            }
            if ($mode === 'in') {
                if ($qty <= 0) {
                    throw new \InvalidArgumentException('Количество прихода должно быть больше нуля.');
                }
                Product::addStock($id, $qty, $unitCost, $reason, Auth::id());
                Audit::log('stock.in', ['id' => $id, 'name' => $product['name'], 'qty' => $qty, 'reason' => $reason]);
                flash('success', 'Приход оформлен: +' . $qty . ' ' . $product['unit'] . ' «' . $product['name'] . '».');
            } else {
                $allowNegative = \PIZ\Settings::get('allow_negative_stock', '1') === '1';
                if ($qty < 0 && !$allowNegative) {
                    throw new \InvalidArgumentException('Отрицательные остатки запрещены в настройках.');
                }
                Product::setStock($id, $qty, $reason, Auth::id());
                Audit::log('stock.adjust', ['id' => $id, 'name' => $product['name'], 'qty' => $qty, 'reason' => $reason]);
                flash('success', 'Остаток обновлён: «' . $product['name'] . '» — ' . $qty . ' ' . $product['unit'] . '.');
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/products');
    }

    public function import(): void
    {
        Auth::requirePermission('products.manage');

        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash('error', 'Выберите файл .xlsx или .csv.');
            redirect('/products');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            flash('error', 'Не удалось загрузить файл (код ' . (int) $file['error'] . ').');
            redirect('/products');
        }
        if ((int) $file['size'] > 10 * 1024 * 1024) {
            flash('error', 'Файл слишком большой. Максимум 10 МБ.');
            redirect('/products');
        }

        try {
            $result = Importer::importFile(
                (string) $file['tmp_name'],
                (string) $file['name'],
                Auth::id(),
                ['update_prices' => !empty($_POST['update_prices'])]
            );
            $message = 'Импорт завершён: добавлено ' . $result['added'] . ', обновлено ' . $result['updated'] . ', пропущено ' . $result['skipped'] . '.';
            flash('success', $message);
            if ($result['errors'] !== []) {
                flash('error', implode(' ', array_slice($result['errors'], 0, 5)));
            }
            Audit::log('product.import', ['file' => $file['name'], 'result' => $result]);
        } catch (\Throwable $e) {
            flash('error', 'Импорт не выполнен: ' . $e->getMessage());
        }
        redirect('/products');
    }

    public function template(): void
    {
        Auth::requirePermission('products.manage');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products-template.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Наименование', 'Закупка', 'Продажа', 'Остаток', 'Категория', 'Артикул', 'Ед.', 'Мин. остаток'], ';', '"', '');
        fputcsv($out, ['Бон аква 1 л', '50', '90', '12', 'Напитки', 'A-001', 'шт', '3'], ';', '"', '');
        fputcsv($out, ['Шаурма классическая', '120', '250', '0', 'Фастфуд', 'A-002', 'шт', '5'], ';', '"', '');
        fclose($out);
        exit;
    }

    public function export(): void
    {
        Auth::requirePermission('products.view');

        $filters = [
            'search' => trim((string) ($_GET['q'] ?? '')),
            'category' => trim((string) ($_GET['category'] ?? '')),
            'low_only' => isset($_GET['low']),
            'include_archived' => isset($_GET['archived']),
        ];
        $products = Product::all($filters);
        $finance = Auth::can('finance.view');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products-' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        $header = ['Артикул', 'Наименование', 'Категория'];
        if ($finance) {
            $header[] = 'Закупка';
        }
        $header = array_merge($header, ['Цена продажи', 'Остаток', 'Ед.', 'Мин. остаток', 'В продаже']);
        fputcsv($out, $header, ';', '"', '');
        foreach ($products as $product) {
            $row = [
                csv_safe($product['sku']),
                csv_safe($product['name']),
                csv_safe($product['category']),
            ];
            if ($finance) {
                $row[] = kop_to_input((int) $product['purchase_price_kop']);
            }
            $row = array_merge($row, [
                kop_to_input((int) $product['sale_price_kop']),
                (int) $product['stock_qty'],
                $product['unit'],
                (int) $product['low_stock'],
                ((int) $product['active'] === 1) ? 'Да' : 'Нет',
            ]);
            fputcsv($out, $row, ';', '"', '');
        }
        fclose($out);
        exit;
    }
}
