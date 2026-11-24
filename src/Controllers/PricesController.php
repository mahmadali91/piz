<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Models\Product;

final class PricesController
{
    public function index(): void
    {
        Auth::requirePermission('prices.manage');
        redirect('/products?mass=1');
    }

    public function save(): void
    {
        Auth::requirePermission('prices.manage');

        $prices = $_POST['prices'] ?? [];
        if (!is_array($prices)) {
            flash('error', 'Некорректные данные цен.');
            redirect('/products');
        }

        $updated = 0;
        $errors = [];
        foreach ($prices as $id => $row) {
            $productId = (int) $id;
            $product = Product::find($productId);
            if (!$product) {
                continue;
            }
            $purchase = parse_money($row['purchase'] ?? '');
            $sale = parse_money($row['sale'] ?? '');
            if ($purchase === null || $sale === null || $purchase < 0 || $sale < 0) {
                $errors[] = '«' . $product['name'] . '»: проверьте цены.';
                continue;
            }
            Product::updatePrices($productId, $purchase, $sale, Auth::id(), 'manual');
            $updated++;
        }

        Audit::log('prices.update', ['count' => $updated]);
        if ($errors !== []) {
            flash('error', implode(' ', array_slice($errors, 0, 5)));
        }
        flash('success', 'Сохранено цен: ' . $updated . '.');
        redirect('/products');
    }

    public function bulk(): void
    {
        Auth::requirePermission('prices.manage');

        $target = (string) ($_POST['target'] ?? 'sale');
        $mode = (string) ($_POST['mode'] ?? 'percent');
        $raw = trim((string) ($_POST['value'] ?? ''));
        if (!in_array($target, ['sale', 'purchase', 'both'], true)) {
            $target = 'sale';
        }
        if (!in_array($mode, ['percent', 'amount', 'set'], true)) {
            $mode = 'percent';
        }
        $raw = str_replace([' ', ','], ['', '.'], $raw);
        if ($raw === '' || !is_numeric($raw)) {
            flash('error', 'Укажите числовое значение для массового изменения.');
            redirect('/products');
        }
        $number = (float) $raw;
        $amountKop = $mode === 'percent' ? 0 : (int) round($number * 100);
        if ($mode !== 'percent' && $amountKop < 0) {
            flash('error', 'Сумма не может быть отрицательной.');
            redirect('/products');
        }

        $products = Product::all();
        $updated = 0;
        foreach ($products as $product) {
            $purchase = (int) $product['purchase_price_kop'];
            $sale = (int) $product['sale_price_kop'];

            $apply = static function (int $current) use ($mode, $number, $amountKop): int {
                if ($mode === 'percent') {
                    return max(0, (int) round($current * (1 + $number / 100)));
                }
                if ($mode === 'amount') {
                    return max(0, $current + $amountKop);
                }
                return max(0, $amountKop);
            };

            if ($target === 'sale' || $target === 'both') {
                $sale = $apply($sale);
            }
            if ($target === 'purchase' || $target === 'both') {
                $purchase = $apply($purchase);
            }
            Product::updatePrices((int) $product['id'], $purchase, $sale, Auth::id(), 'bulk', 'Массовое изменение цен');
            $updated++;
        }

        Audit::log('prices.bulk', ['target' => $target, 'mode' => $mode, 'value' => $number, 'count' => $updated]);
        flash('success', 'Массово обновлено товаров: ' . $updated . '.');
        redirect('/products');
    }
}
