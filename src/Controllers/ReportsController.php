<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Auth;
use PIZ\Models\Product;
use PIZ\Models\Sale;
use PIZ\View;

final class ReportsController
{
    public function index(): void
    {
        Auth::requirePermission('reports.view');

        $period = period_range();
        $start = $period['start'];
        $end = $period['end'];

        $summary = Sale::rangeTotals($start, $end);
        $series = Sale::dailySeries($start, $end);
        $payments = Sale::paymentBreakdown($start, $end);
        $topProducts = Sale::topProducts($start, $end, 20);
        $stock = Product::stats();
        $lowStock = Product::lowStock(20);
        $canceled = Sale::canceledCount($start, $end);

        $days = max(1, (int) round((strtotime($end) - strtotime($start)) / 86400));
        $previousEnd = $start;
        $previousStart = date('Y-m-d 00:00:00', strtotime($start . ' -' . $days . ' days'));
        $previous = Sale::rangeTotals($previousStart, $previousEnd);

        $margin = $summary['revenue'] > 0 ? round($summary['profit'] / $summary['revenue'] * 100, 1) : 0.0;
        $paymentsTotal = array_sum(array_column($payments, 'revenue'));

        View::render('reports', [
            'title' => 'Отчёты',
            'active' => 'reports',
            'user' => Auth::user(),
            'period' => $period,
            'summary' => $summary,
            'previous' => $previous,
            'series' => $series,
            'payments' => $payments,
            'paymentsTotal' => $paymentsTotal,
            'topProducts' => $topProducts,
            'stock' => $stock,
            'lowStock' => $lowStock,
            'canceled' => $canceled,
            'margin' => $margin,
            'days' => $days,
        ]);
    }

    public function export(): void
    {
        Auth::requirePermission('reports.view');

        $period = period_range();
        $type = (string) ($_GET['type'] ?? 'daily');
        $start = $period['start'];
        $end = $period['end'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="report-' . $type . '-' . $period['from'] . '-' . $period['to'] . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');

        if ($type === 'products') {
            fputcsv($out, ['Товар', 'Продано, шт', 'Выручка', 'Прибыль'], ';', '"', '');
            foreach (Sale::topProducts($start, $end, 50) as $row) {
                fputcsv($out, [
                    csv_safe($row['name']),
                    (int) $row['qty'],
                    kop_to_input((int) $row['revenue']),
                    kop_to_input((int) $row['revenue'] - (int) $row['cost']),
                ], ';');
            }
        } elseif ($type === 'payments') {
            fputcsv($out, ['Способ оплаты', 'Продаж', 'Выручка'], ';', '"', '');
            foreach (Sale::paymentBreakdown($start, $end) as $method => $row) {
                fputcsv($out, [payment_label($method), $row['count'], kop_to_input($row['revenue'])], ';', '"', '');
            }
        } elseif ($type === 'stock') {
            fputcsv($out, ['Артикул', 'Товар', 'Остаток', 'Ед.', 'Закупка', 'Цена', 'Стоимость закупки', 'Стоимость продажи'], ';', '"', '');
            foreach (Product::all(['include_archived' => true]) as $product) {
                fputcsv($out, [
                    csv_safe($product['sku']),
                    csv_safe($product['name']),
                    (int) $product['stock_qty'],
                    $product['unit'],
                    kop_to_input((int) $product['purchase_price_kop']),
                    kop_to_input((int) $product['sale_price_kop']),
                    kop_to_input((int) $product['stock_qty'] * (int) $product['purchase_price_kop']),
                    kop_to_input((int) $product['stock_qty'] * (int) $product['sale_price_kop']),
                ], ';');
            }
        } else {
            fputcsv($out, ['Дата', 'Продаж', 'Выручка', 'Прибыль'], ';', '"', '');
            foreach (Sale::dailySeries($start, $end) as $date => $row) {
                fputcsv($out, [date('d.m.Y', strtotime($date)), $row['count'], kop_to_input($row['revenue']), kop_to_input($row['profit'])], ';', '"', '');
            }
            $summary = Sale::rangeTotals($start, $end);
            fputcsv($out, [
                'ИТОГО',
                $summary['count'],
                kop_to_input($summary['revenue']),
                kop_to_input($summary['profit']),
            ], ';');
        }

        fclose($out);
        exit;
    }
}
