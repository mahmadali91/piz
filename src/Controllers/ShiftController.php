<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Models\Product;
use PIZ\Models\Shift;
use PIZ\Services\PdfService;
use PIZ\View;

final class ShiftController
{
    public function index(): void
    {
        Auth::requirePermission('shift.manage');
        $shift = Shift::current();
        $totals = $shift ? Shift::totals((int) $shift['id']) : null;
        $payments = $shift ? Shift::payments((int) $shift['id']) : [];
        $writeoffs = $shift ? Shift::writeoffs((int) $shift['id']) : [];
        $canClose = $shift
            ? (Auth::isAdmin() || (int) $shift['opened_by'] === Auth::id())
            : false;

        View::render('shift/index', [
            'title' => 'Смена',
            'active' => 'shift',
            'user' => Auth::user(),
            'shift' => $shift,
            'totals' => $totals,
            'payments' => $payments,
            'writeoffs' => $writeoffs,
            'canClose' => $canClose,
            'stock' => Product::stats(),
            'finance' => Auth::can('finance.view'),
        ]);
    }

    public function open(): void
    {
        Auth::requirePermission('shift.manage');
        try {
            $shift = Shift::open(Auth::id());
            Audit::log('shift.open', ['id' => (int) $shift['id']]);
            flash('success', 'Смена открыта.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/shift');
    }

    public function close(): void
    {
        Auth::requirePermission('shift.manage');
        $shift = Shift::current();
        if (!$shift) {
            flash('error', 'Открытой смены нет.');
            redirect('/shift');
        }
        $note = trim((string) ($_POST['note'] ?? ''));
        try {
            $closed = Shift::close((int) $shift['id'], Auth::id(), $note, Auth::isAdmin());
            Audit::log('shift.close', [
                'id' => (int) $closed['id'],
                'revenue' => (int) $closed['revenue_kop'],
                'expense' => (int) $closed['expense_kop'],
                'stock_units' => (int) $closed['stock_units'],
            ]);
            flash('success', 'Смена закрыта. Отчёт по остаткам готов.');
            redirect('/shifts/' . $closed['id']);
        } catch (\Throwable $e) {
            flash('error', 'Не удалось закрыть смену: ' . $e->getMessage());
            redirect('/shift');
        }
    }

    public function history(): void
    {
        Auth::requirePermission('shift.manage');
        $perPage = 30;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $onlyUser = Auth::can('sales.view_all') ? null : Auth::id();
        $total = Shift::count($onlyUser);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $shifts = Shift::list($perPage, ($page - 1) * $perPage, $onlyUser);

        View::render('shift/history', [
            'title' => 'История смен',
            'active' => 'shift',
            'user' => Auth::user(),
            'shifts' => $shifts,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'finance' => Auth::can('finance.view'),
        ]);
    }

    public function report(string $id): void
    {
        Auth::requirePermission('shift.manage');
        $shift = Shift::find((int) $id);
        if (!$shift) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Смена не найдена'], 'layout');
            return;
        }
        if (!Auth::can('sales.view_all') && (int) $shift['opened_by'] !== Auth::id()) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Нет доступа'], 'layout');
            return;
        }
        $stock = Shift::stock((int) $shift['id']);
        View::render('shift/report', [
            'title' => 'Смена №' . $shift['id'],
            'active' => 'shift',
            'user' => Auth::user(),
            'shift' => $shift,
            'stock' => $stock,
            'stockTotals' => Shift::stockTotals((int) $shift['id']),
            'payments' => Shift::payments((int) $shift['id']),
            'writeoffs' => Shift::writeoffs((int) $shift['id']),
            'totals' => [
                'count' => (int) $shift['sales_count'],
                'revenue' => (int) $shift['revenue_kop'],
                'cost' => (int) $shift['cost_kop'],
                'expense' => (int) $shift['expense_kop'],
                'profit' => (int) $shift['profit_kop'],
            ],
            'finance' => Auth::can('finance.view'),
        ]);
    }

    public function pdf(string $id): void
    {
        Auth::requirePermission('shift.manage');
        $shift = Shift::find((int) $id);
        if (!$shift) {
            http_response_code(404);
            return;
        }
        if (!Auth::can('sales.view_all') && (int) $shift['opened_by'] !== Auth::id()) {
            http_response_code(403);
            return;
        }
        $totals = [
            'count' => (int) $shift['sales_count'],
            'revenue' => (int) $shift['revenue_kop'],
            'cost' => (int) $shift['cost_kop'],
            'expense' => (int) $shift['expense_kop'],
            'profit' => (int) $shift['profit_kop'],
        ];
        PdfService::shift(
            $shift,
            $totals,
            Shift::stock((int) $shift['id']),
            Shift::payments((int) $shift['id']),
            Shift::writeoffs((int) $shift['id'])
        );
    }

    public function export(string $id): void
    {
        Auth::requirePermission('shift.manage');
        $shift = Shift::find((int) $id);
        if (!$shift) {
            http_response_code(404);
            return;
        }
        if (!Auth::can('sales.view_all') && (int) $shift['opened_by'] !== Auth::id()) {
            http_response_code(403);
            return;
        }
        $stock = Shift::stock((int) $shift['id']);
        $payments = Shift::payments((int) $shift['id']);
        $finance = Auth::can('finance.view');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="shift-' . (int) $shift['id'] . '-' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        $row = static function (array $cells) use ($out): void {
            fputcsv($out, $cells, ';', '"', '');
        };

        $row(['Смена №', $shift['id']]);
        $row(['Открыта', dt_human($shift['opened_at'])]);
        $row(['Закрыта', dt_human($shift['closed_at'])]);
        $row(['Открыл', $shift['opened_by_name'] ?? '']);
        $row(['Закрыл', $shift['closed_by_name'] ?? '']);
        $row(['Продаж', (int) $shift['sales_count']]);
        $row(['Выручка', kop_to_input((int) $shift['revenue_kop'])]);
        if ($finance) {
            $row(['Себестоимость продаж', kop_to_input((int) $shift['cost_kop'])]);
            $row(['Расходы (списания)', kop_to_input((int) $shift['expense_kop'])]);
            $row(['Прибыль', kop_to_input((int) $shift['profit_kop'])]);
            $row(['Стоимость остатка', kop_to_input((int) $shift['stock_value_kop'])]);
        }
        $row(['Остаток единиц', (int) $shift['stock_units']]);
        $row([]);
        $row(['Оплата', 'Продаж', 'Сумма']);
        foreach ($payments as $method => $payment) {
            $row([payment_label($method), $payment['count'], kop_to_input($payment['revenue'])]);
        }
        $row([]);
        $row(['Остаток на конец смены']);
        $header = ['Товар', 'Артикул', 'Кол-во'];
        if ($finance) {
            $header[] = 'Себестоимость';
            $header[] = 'Цена';
            $header[] = 'Стоимость остатка';
        }
        $row($header);
        foreach ($stock as $item) {
            $cells = [csv_safe($item['name']), csv_safe($item['sku']), (int) $item['qty']];
            if ($finance) {
                $cells[] = kop_to_input((int) $item['unit_cost_kop']);
                $cells[] = kop_to_input((int) $item['sale_price_kop']);
                $cells[] = kop_to_input((int) $item['stock_value_kop']);
            }
            $row($cells);
        }
        fclose($out);
        exit;
    }
}
