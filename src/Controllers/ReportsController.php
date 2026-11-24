<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Auth;
use PIZ\Models\Product;
use PIZ\Models\Sale;
use PIZ\Services\PdfService;
use PIZ\View;

final class ReportsController
{
    public function index(): void
    {
        Auth::requirePermission('reports.view');
        $params = [];
        foreach (['period', 'from', 'to'] as $key) {
            if (isset($_GET[$key])) {
                $params[$key] = (string) $_GET[$key];
            }
        }
        redirect(query_url('/', $params));
    }

    public function pdf(): void
    {
        Auth::requirePermission('reports.view');
        $period = period_range();
        $summary = Sale::rangeTotals($period['start'], $period['end']);
        $payments = Sale::paymentBreakdown($period['start'], $period['end']);
        $topProducts = Sale::topProducts($period['start'], $period['end'], 20);
        $stock = Product::stats();
        PdfService::report(
            'Период: ' . date('d.m.Y', strtotime($period['from'])) . ' — ' . date('d.m.Y', strtotime($period['to'])),
            $summary,
            $payments,
            $topProducts,
            $stock
        );
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
                ], ';', '"', '');
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
            fputcsv($out, ['Дата', 'Продаж', 'Выручка', 'Расходы', 'Прибыль'], ';', '"', '');
            foreach (Sale::dailySeries($start, $end) as $date => $row) {
                fputcsv($out, [
                    date('d.m.Y', strtotime($date)),
                    $row['count'],
                    kop_to_input($row['revenue']),
                    kop_to_input($row['expense'] ?? 0),
                    kop_to_input($row['profit']),
                ], ';', '"', '');
            }
            $summary = Sale::rangeTotals($start, $end);
            fputcsv($out, [
                'ИТОГО',
                $summary['count'],
                kop_to_input($summary['revenue']),
                kop_to_input((int) $summary['expense']),
                kop_to_input($summary['profit']),
            ], ';', '"', '');
        }

        fclose($out);
        exit;
    }
}
