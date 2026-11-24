<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Auth;
use PIZ\Models\Product;
use PIZ\Services\ImageUploader;
use PIZ\View;

final class PosController
{
    public function index(): void
    {
        Auth::requirePermission('pos.use');

        $products = Product::all();
        $payload = [];
        foreach ($products as $product) {
            $payload[] = [
                'id' => (int) $product['id'],
                'name' => (string) $product['name'],
                'price' => (int) $product['sale_price_kop'],
                'stock' => (int) $product['stock_qty'],
                'low' => (int) $product['low_stock'],
                'category' => (string) $product['category'],
                'sku' => (string) ($product['sku'] ?? ''),
                'photo' => ImageUploader::url($product['photo'] ?? null),
                'unit' => (string) $product['unit'],
            ];
        }

        View::render('pos', [
            'title' => 'Касса',
            'active' => 'pos',
            'user' => Auth::user(),
            'products' => $products,
            'payload' => $payload,
            'categories' => Product::categories(),
            'allowNegative' => \PIZ\Settings::get('allow_negative_stock', '1') === '1',
            'pageScript' => 'assets/js/pos.js',
        ]);
    }
}
