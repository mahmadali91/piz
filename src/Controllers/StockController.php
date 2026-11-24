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

        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = (int) ($_POST['qty'] ?? 0);
        $unitCost = parse_money($_POST['unit_cost'] ?? '');
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if (!Auth::can('prices.manage')) {
            $unitCost = null;
        }

        try {
            Product::addStock($productId, $qty, $unitCost, 'Приход товара', Auth::id(), $comment);
            $product = Product::find($productId);
            Audit::log('stock.in', [
                'id' => $productId,
                'name' => $product['name'] ?? '',
                'qty' => $qty,
                'cost' => $unitCost,
            ]);
            flash('success', 'Приход оформлен: +' . $qty . ' «' . ($product['name'] ?? '') . '».');
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

        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = (int) ($_POST['qty'] ?? 0);
        $employee = trim((string) ($_POST['employee_name'] ?? ''));
        $reason = (string) ($_POST['reason'] ?? 'Другое');
        $comment = trim((string) ($_POST['comment'] ?? ''));

        try {
            Writeoff::create($productId, $qty, $employee, $reason, $comment, Auth::id());
            Audit::log('stock.writeoff', [
                'product_id' => $productId,
                'qty' => $qty,
                'employee' => $employee,
                'reason' => $reason,
            ]);
            flash('success', 'Списание оформлено как расход: ' . $qty . ' шт.');
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
