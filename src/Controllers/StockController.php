<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Database;
use PIZ\Models\Product;
use PIZ\Models\Receipt;
use PIZ\Models\Writeoff;
use PIZ\Settings;
use PIZ\View;

final class StockController
{
    public function index(): void
    {
        Auth::requireLogin();
        if (!Auth::canAny(['products.view', 'stock.manage'])) {
            Auth::requirePermission('stock.manage');
        }

        $filters = [
            'search' => trim((string) ($_GET['q'] ?? '')),
            'category' => trim((string) ($_GET['category'] ?? '')),
            'low_only' => isset($_GET['low']),
        ];
        $products = Product::all($filters);
        $movements = Database::fetchAll(
            'SELECT m.*, p.name AS product_name, p.unit, u.name AS user_name
             FROM stock_movements m
             LEFT JOIN products p ON p.id = m.product_id
             LEFT JOIN users u ON u.id = m.user_id
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT 120'
        );

        View::render('stock/index', [
            'title' => 'Остаток',
            'active' => 'products',
            'user' => Auth::user(),
            'products' => $products,
            'categories' => Product::categories(),
            'filters' => $filters,
            'stats' => Product::stats(),
            'movements' => $movements,
            'canAdjust' => Auth::can('stock.manage'),
            'finance' => Auth::can('finance.view'),
            'allowNegative' => Settings::get('allow_negative_stock', '1') === '1',
        ]);
    }

    public function in(): void
    {
        Auth::requirePermission('stock.manage');

        View::render('stock/in', [
            'title' => 'Приход',
            'active' => 'stock_in',
            'user' => Auth::user(),
            'products' => Product::all(),
            'receipts' => Receipt::list(100),
            'receiptTotals' => Receipt::totals(),
            'finance' => Auth::can('finance.view'),
            'canPrices' => Auth::can('prices.manage'),
        ]);
    }

    public function inStore(): void
    {
        Auth::requirePermission('stock.manage');

        $productIds = $_POST['product_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $costs = $_POST['unit_cost'] ?? [];
        if (!is_array($productIds)) {
            $productIds = [$productIds];
            $qtys = [is_array($qtys) ? ($qtys[0] ?? 0) : $qtys];
            $costs = [is_array($costs) ? ($costs[0] ?? '') : $costs];
        }
        if (!is_array($qtys)) {
            $qtys = [$qtys];
        }
        if (!is_array($costs)) {
            $costs = [$costs];
        }

        $comment = trim((string) ($_POST['comment'] ?? ''));
        $canPrices = Auth::can('prices.manage');
        try {
        $items = [];
        foreach ($productIds as $index => $productId) {
            $productId = (int) $productId;
            $qty = (int) ($qtys[$index] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            if ($qty <= 0 || $qty > 100000) {
                throw new \InvalidArgumentException('Количество должно быть от 1 до 100000.');
            }
            $cost = $canPrices ? parse_money($costs[$index] ?? '') : null;
            $items[] = ['product_id' => $productId, 'qty' => $qty, 'unit_cost_kop' => $cost];
        }

            if ($items === []) {
                throw new \InvalidArgumentException('Добавьте хотя бы один товар.');
            }
            Product::addStockMany($items, 'Приход товара', Auth::id(), $comment);
            $totalQty = array_sum(array_column($items, 'qty'));
            Audit::log('stock.in', ['items' => $items, 'comment' => $comment]);
            flash('success', 'Приход оформлен: ' . count($items) . ' ' . plural(count($items), 'позиция', 'позиции', 'позиций') . ', +' . $totalQty . ' ед.');
        } catch (\Throwable $e) {
            flash('error', 'Не удалось оформить приход: ' . $e->getMessage());
        }
        redirect('/stock/in');
    }

    public function out(): void
    {
        Auth::requirePermission('writeoff.manage');

        View::render('stock/out', [
            'title' => 'Расход',
            'active' => 'stock_out',
            'user' => Auth::user(),
            'products' => Product::all(),
            'writeoffs' => Writeoff::list(100),
            'employees' => Database::fetchAll('SELECT id, name FROM users WHERE active = 1 ORDER BY name'),
            'reasons' => Writeoff::REASONS,
            'finance' => Auth::can('finance.view'),
            'allowNegative' => Settings::get('allow_negative_stock', '1') === '1',
        ]);
    }

    public function outStore(): void
    {
        Auth::requirePermission('writeoff.manage');

        $productIds = $_POST['product_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        if (!is_array($productIds)) {
            $productIds = [$productIds];
            $qtys = [is_array($qtys) ? ($qtys[0] ?? 0) : $qtys];
        }
        if (!is_array($qtys)) {
            $qtys = [$qtys];
        }
        $employee = trim((string) ($_POST['employee_name'] ?? ''));
        $reason = (string) ($_POST['reason'] ?? 'Другое');
        $comment = trim((string) ($_POST['comment'] ?? ''));

        try {
        $items = [];
        foreach ($productIds as $index => $productId) {
            $productId = (int) $productId;
            $qty = (int) ($qtys[$index] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            if ($qty <= 0 || $qty > 100000) {
                throw new \InvalidArgumentException('Количество должно быть от 1 до 100000.');
            }
            $items[] = ['product_id' => $productId, 'qty' => $qty];
        }

            if ($items === []) {
                throw new \InvalidArgumentException('Добавьте хотя бы один товар.');
            }
            Writeoff::createMany($items, $employee, $reason, $comment, Auth::id());
            $totalQty = array_sum(array_column($items, 'qty'));
            Audit::log('stock.writeoff', ['items' => $items, 'employee' => $employee, 'reason' => $reason]);
            flash('success', 'Расход оформлен: ' . count($items) . ' ' . plural(count($items), 'позиция', 'позиции', 'позиций') . ', -' . $totalQty . ' ед.');
        } catch (\Throwable $e) {
            flash('error', 'Не удалось оформить расход: ' . $e->getMessage());
        }
        redirect('/stock/out');
    }

    public function adjust(): void
    {
        Auth::requirePermission('stock.manage');

        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = (int) ($_POST['qty'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $allowNegative = Settings::get('allow_negative_stock', '1') === '1';

        try {
            if ($qty < 0 && !$allowNegative) {
                throw new \InvalidArgumentException('Отрицательные остатки запрещены в настройках.');
            }
            Product::setStock($productId, $qty, $reason, Auth::id());
            $product = Product::find($productId);
            Audit::log('stock.adjust', ['id' => $productId, 'name' => $product['name'] ?? '', 'qty' => $qty, 'reason' => $reason]);
            flash('success', 'Остаток «' . ($product['name'] ?? '') . '» установлен: ' . $qty . '.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/stock');
    }
}
