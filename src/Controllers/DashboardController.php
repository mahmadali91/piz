<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Auth;
use PIZ\Models\Product;
use PIZ\Models\Sale;
use PIZ\Models\Shift;
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

        $canReports = Auth::can('reports.view');
        $reportData = [];
        if ($canReports) {
            if (!isset($_GET['period']) || $_GET['period'] === '') {
                $_GET['period'] = 'today';
            }
            $reportPeriod = period_range();
            $reportSummary = Sale::rangeTotals($reportPeriod['start'], $reportPeriod['end']);
            $reportSeries = Sale::dailySeries($reportPeriod['start'], $reportPeriod['end']);
            $reportPayments = Sale::paymentBreakdown($reportPeriod['start'], $reportPeriod['end']);
            $reportTopProducts = Sale::topProducts($reportPeriod['start'], $reportPeriod['end'], 20);
            $reportLowStock = Product::lowStock(20);
            $reportCanceled = Sale::canceledCount($reportPeriod['start'], $reportPeriod['end']);
            $reportDays = max(1, (int) round((strtotime($reportPeriod['end']) - strtotime($reportPeriod['start'])) / 86400));
            $reportPreviousEnd = $reportPeriod['start'];
            $reportPreviousStart = date('Y-m-d 00:00:00', strtotime($reportPeriod['start'] . ' -' . $reportDays . ' days'));
            $reportPrevious = Sale::rangeTotals($reportPreviousStart, $reportPreviousEnd);
            $reportMargin = $reportSummary['revenue'] > 0 ? round($reportSummary['profit'] / $reportSummary['revenue'] * 100, 1) : 0.0;
            $reportData = [
                'period' => $reportPeriod,
                'summary' => $reportSummary,
                'previous' => $reportPrevious,
                'series' => $reportSeries,
                'payments' => $reportPayments,
                'paymentsTotal' => array_sum(array_column($reportPayments, 'revenue')),
                'topProducts' => $reportTopProducts,
                'stock' => $stock,
                'lowStock' => $reportLowStock,
                'canceled' => $reportCanceled,
                'margin' => $reportMargin,
                'days' => $reportDays,
                'finance' => Auth::can('finance.view'),
            ];
        }

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
            'currentShift' => Shift::current(),
            'canReports' => $canReports,
            'reportData' => $reportData,
        ]);
    }
}
