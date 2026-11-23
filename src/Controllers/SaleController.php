<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Models\Sale;
use PIZ\View;

final class SaleController
{
    public function index(): void
    {
        Auth::requireLogin();

        $period = period_range();
        $payment = (string) ($_GET['payment'] ?? '');
        $status = (string) ($_GET['status'] ?? '');
        $search = trim((string) ($_GET['q'] ?? ''));

        $filters = [
            'from' => $period['from'],
            'to' => $period['to'],
            'payment' => in_array($payment, Sale::PAYMENT_METHODS, true) ? $payment : '',
            'status' => in_array($status, ['completed', 'canceled'], true) ? $status : '',
            'search' => $search,
        ];
        $canViewAll = Auth::can('sales.view_all');
        if (!$canViewAll) {
            $filters['user_id'] = Auth::id();
        }

        $perPage = 30;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = Sale::count($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $sales = Sale::list($filters, $perPage, ($page - 1) * $perPage);
        $summary = Sale::summary($filters);

        View::render('sales/index', [
            'title' => 'Продажи',
            'active' => 'sales',
            'user' => Auth::user(),
            'sales' => $sales,
            'summary' => $summary,
            'filters' => $filters,
            'period' => $period,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'finance' => Auth::can('finance.view'),
        ]);
    }

    public function show(string $id): void
    {
        Auth::requireLogin();
        $sale = Sale::find((int) $id);
        if (!$sale) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Продажа не найдена'], 'layout');
            return;
        }
        if (!Auth::can('sales.view_all') && (int) $sale['user_id'] !== Auth::id()) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Нет доступа'], 'layout');
            return;
        }

        View::render('sales/show', [
            'title' => 'Продажа ' . $sale['number'],
            'active' => 'sales',
            'user' => Auth::user(),
            'sale' => $sale,
            'finance' => Auth::can('finance.view'),
            'canCancel' => Auth::can('sales.cancel'),
        ]);
    }

    public function cancel(string $id): void
    {
        Auth::requirePermission('sales.cancel');
        $saleId = (int) $id;
        $sale = Sale::find($saleId);
        if (!$sale) {
            flash('error', 'Продажа не найдена.');
            redirect('/sales');
        }
        try {
            Sale::cancel($saleId, Auth::id());
            Audit::log('sale.cancel', ['id' => $saleId, 'number' => $sale['number'], 'total' => (int) $sale['total_kop']]);
            flash('success', 'Продажа ' . $sale['number'] . ' отменена, товары возвращены на склад.');
        } catch (\Throwable $e) {
            flash('error', 'Не удалось отменить продажу: ' . $e->getMessage());
        }
        redirect('/sales/' . $saleId);
    }

    public function createApi(): void
    {
        Auth::requirePermission('pos.use');
        $request = request_post();

        $items = $request['items'] ?? [];
        if (!is_array($items)) {
            json_response(['ok' => false, 'error' => 'Некорректный состав чека.'], 422);
        }

        $payment = (string) ($request['payment_method'] ?? 'cash');
        $discount = max(0, (int) ($request['discount_kop'] ?? 0));
        $received = null;
        if ($payment === 'cash') {
            $rawReceived = $request['received_kop'] ?? null;
            if ($rawReceived !== null && $rawReceived !== '') {
                $received = max(0, (int) $rawReceived);
            }
        }

        $opts = [
            'payment_method' => $payment,
            'discount_kop' => $discount,
            'received_kop' => $received,
            'customer' => (string) ($request['customer'] ?? ''),
            'comment' => (string) ($request['comment'] ?? ''),
            'token' => (string) ($request['token'] ?? ''),
        ];

        try {
            $result = Sale::create($items, $opts, Auth::id());
            $sale = $result['sale'];
            if (!$result['duplicate']) {
                Audit::log('sale.create', [
                    'id' => (int) $sale['id'],
                    'number' => $sale['number'],
                    'total' => (int) $sale['total_kop'],
                    'payment' => $sale['payment_method'],
                    'items' => count($sale['items']),
                ]);
            }

            $receiptHtml = View::capture('partials/receipt', ['sale' => $sale]);

            json_response([
                'ok' => true,
                'duplicate' => $result['duplicate'],
                'sale' => [
                    'id' => (int) $sale['id'],
                    'number' => $sale['number'],
                    'total_kop' => (int) $sale['total_kop'],
                    'discount_kop' => (int) $sale['discount_kop'],
                    'payment_method' => $sale['payment_method'],
                    'received_kop' => (int) $sale['received_kop'],
                    'change_kop' => (int) $sale['change_kop'],
                    'created_at' => $sale['created_at'],
                    'items_count' => (int) $sale['items_count'],
                    'customer' => $sale['customer'],
                ],
                'receipt_html' => $receiptHtml,
                'sale_url' => url('/sales/' . $sale['id']),
            ]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[PIZ] sale create: ' . $e->getMessage());
            json_response(['ok' => false, 'error' => 'Не удалось оформить продажу. Попробуйте ещё раз.'], 500);
        }
    }

    public function export(): void
    {
        Auth::requireLogin();

        $period = period_range();
        $payment = (string) ($_GET['payment'] ?? '');
        $status = (string) ($_GET['status'] ?? '');
        $filters = [
            'from' => $period['from'],
            'to' => $period['to'],
            'payment' => in_array($payment, Sale::PAYMENT_METHODS, true) ? $payment : '',
            'status' => in_array($status, ['completed', 'canceled'], true) ? $status : '',
            'search' => trim((string) ($_GET['q'] ?? '')),
        ];
        if (!Auth::can('sales.view_all')) {
            $filters['user_id'] = Auth::id();
        }

        $sales = Sale::list($filters, 5000, 0);
        $finance = Auth::can('finance.view');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sales-' . $period['from'] . '-' . $period['to'] . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        $header = ['Номер', 'Дата и время', 'Кассир', 'Позиций', 'Оплата', 'Сумма'];
        if ($finance) {
            $header[] = 'Себестоимость';
            $header[] = 'Прибыль';
            $header[] = 'Скидка';
        }
        $header[] = 'Статус';
        fputcsv($out, $header, ';', '"', '');

        foreach ($sales as $sale) {
            $row = [
                csv_safe($sale['number']),
                dt_human($sale['created_at']),
                csv_safe($sale['cashier_name'] ?? '—'),
                (int) $sale['items_count'],
                payment_label((string) $sale['payment_method']),
                kop_to_input((int) $sale['total_kop']),
            ];
            if ($finance) {
                $row[] = kop_to_input((int) $sale['cost_kop']);
                $row[] = kop_to_input((int) $sale['total_kop'] - (int) $sale['cost_kop']);
                $row[] = kop_to_input((int) $sale['discount_kop']);
            }
            $row[] = $sale['status'] === 'canceled' ? 'Отменена' : 'Завершена';
            fputcsv($out, $row, ';', '"', '');
        }
        fclose($out);
        exit;
    }
}
