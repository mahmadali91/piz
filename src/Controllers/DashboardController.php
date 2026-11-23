<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Auth;
use PIZ\Models\Product;
use PIZ\Models\Sale;
use PIZ\View;

final class DashboardController
{
    public function index(): void
    {
        if (!Auth::can('dashboard.view')) {
            if (Auth::can('pos.use')) {
                redirect('/pos');
            }
            Auth::requirePermission('dashboard.view');
        }

        $today = today();
        $todayStart = $today . ' 00:00:00';
        $tomorrow = date('Y-m-d 00:00:00', strtotime($today . ' +1 day'));
        $yesterdayStart = date('Y-m-d 00:00:00', strtotime($today . ' -1 day'));

        $todayTotals = Sale::rangeTotals($todayStart, $tomorrow);
        $yesterdayTotals = Sale::rangeTotals($yesterdayStart, $todayStart);

        $seriesStart = date('Y-m-d', strtotime($today . ' -13 days'));
        $series = Sale::dailySeries($seriesStart . ' 00:00:00', $tomorrow);
        $payments = Sale::paymentBreakdown($todayStart, $tomorrow);
        $paymentsTotal = array_sum(array_column($payments, 'revenue'));

        $stock = Product::stats();
        $lowStock = Product::lowStock(6);
        $recent = Sale::recent(5);
        $topProducts = Sale::topProducts(date('Y-m-d 00:00:00', strtotime($today . ' -6 days')), $tomorrow, 5);
        $canceledToday = Sale::canceledCount($todayStart, $tomorrow);

        $trends = [
            'revenue' => percent_change($todayTotals['revenue'], $yesterdayTotals['revenue']),
            'profit' => percent_change($todayTotals['profit'], $yesterdayTotals['profit']),
            'count' => percent_change($todayTotals['count'], $yesterdayTotals['count']),
            'avg_check' => percent_change($todayTotals['avg_check'], $yesterdayTotals['avg_check']),
        ];

        View::render('dashboard', [
            'title' => 'Дашборд',
            'active' => 'dashboard',
            'user' => Auth::user(),
            'finance' => Auth::can('finance.view'),
            'today' => $todayTotals,
            'yesterday' => $yesterdayTotals,
            'trends' => $trends,
            'series' => $series,
            'payments' => $payments,
            'paymentsTotal' => $paymentsTotal,
            'stock' => $stock,
            'lowStock' => $lowStock,
            'recent' => $recent,
            'topProducts' => $topProducts,
            'canceledToday' => $canceledToday,
        ]);
    }
}
