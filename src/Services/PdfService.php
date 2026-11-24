<?php
declare(strict_types=1);

namespace PIZ\Services;

use PIZ\Models\Sale;
use PIZ\Settings;

final class PdfService
{
    private static function pdf(): \tFPDF\PDF
    {
        require_once PIZ_ROOT . '/src/Lib/tfpdf/PDF.php';
        require_once PIZ_ROOT . '/src/Lib/tfpdf/TTFontFile.php';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', PIZ_ROOT . '/src/Lib/tfpdf/font/');
        }
        if (!defined('FPDF_FONT_WRITE_PATH')) {
            define('FPDF_FONT_WRITE_PATH', PIZ_ROOT . '/src/Lib/tfpdf/font/');
        }
        $pdf = new \tFPDF\PDF('P', 'mm', 'A4');
        $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 14);
        return $pdf;
    }

    private static function header(\tFPDF\PDF $pdf, string $title, array $meta = []): void
    {
        $pdf->AddPage();
        $store = (string) Settings::get('store_name', 'КИОСК ДОНЕР');
        $address = (string) Settings::get('store_address', '');
        $phone = (string) Settings::get('store_phone', '');

        $pdf->SetTextColor(25, 32, 45);
        $pdf->SetFont('DejaVu', 'B', 17);
        $pdf->Cell(0, 8, self::txt($store), 0, 1);
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->SetTextColor(95, 105, 120);
        if ($address !== '') {
            $pdf->Cell(0, 5, self::txt($address), 0, 1);
        }
        if ($phone !== '') {
            $pdf->Cell(0, 5, self::txt($phone), 0, 1);
        }
        $pdf->Ln(2);
        $pdf->SetDrawColor(225, 229, 236);
        $pdf->Line(12, $pdf->GetY(), 198, $pdf->GetY());
        $pdf->Ln(4);
        $pdf->SetTextColor(25, 32, 45);
        $pdf->SetFont('DejaVu', 'B', 14);
        $pdf->Cell(0, 8, self::txt($title), 0, 1);
        if ($meta !== []) {
            $pdf->SetFont('DejaVu', '', 9);
            $pdf->SetTextColor(95, 105, 120);
            foreach ($meta as $line) {
                $pdf->Cell(0, 5, self::txt((string) $line), 0, 1);
            }
        }
        $pdf->Ln(3);
    }

    private static function table(
        \tFPDF\PDF $pdf,
        array $headers,
        array $rows,
        array $widths,
        array $aligns = [],
        int $fontSize = 9
    ): void {
        $pdf->SetFont('DejaVu', 'B', $fontSize);
        $pdf->SetFillColor(244, 246, 249);
        $pdf->SetTextColor(45, 55, 70);
        foreach ($headers as $i => $header) {
            $align = $aligns[$i] ?? 'L';
            $pdf->Cell((float) $widths[$i], 7, self::txt((string) $header), 0, 0, $align, true);
        }
        $pdf->Ln();
        $pdf->SetFont('DejaVu', '', $fontSize);
        $pdf->SetTextColor(25, 32, 45);
        foreach ($rows as $row) {
            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
                $pdf->SetFont('DejaVu', 'B', $fontSize);
                $pdf->SetFillColor(244, 246, 249);
                foreach ($headers as $i => $header) {
                    $pdf->Cell((float) $widths[$i], 7, self::txt((string) $header), 0, 0, $aligns[$i] ?? 'L', true);
                }
                $pdf->Ln();
                $pdf->SetFont('DejaVu', '', $fontSize);
            }
            foreach ($row as $i => $cell) {
                $text = self::fit((string) $cell, (float) $widths[$i], $fontSize);
                $pdf->Cell((float) $widths[$i], 6, self::txt($text), 0, 0, $aligns[$i] ?? 'L');
            }
            $pdf->Ln();
        }
        $pdf->SetDrawColor(225, 229, 236);
        $pdf->Line(12, $pdf->GetY() + 1, 198, $pdf->GetY() + 1);
        $pdf->Ln(3);
    }

    private static function fit(string $text, float $width, int $fontSize): string
    {
        $max = max(8, (int) floor($width / ($fontSize * 0.19)));
        return mb_strlen($text) > $max ? mb_substr($text, 0, max(1, $max - 1)) . '…' : $text;
    }

    private static function txt(string $text): string
    {
        return iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: $text;
    }

    private static function download(\tFPDF\PDF $pdf, string $filename): void
    {
        $data = $pdf->output();
        if (PHP_SAPI !== 'cli') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($data));
        }
        echo $data;
        exit;
    }

    public static function receipt(array $sale, array $items): void
    {
        $pdf = self::pdf();
        self::header($pdf, 'Чек № ' . $sale['number'], [
            'Дата: ' . dt_human($sale['created_at']),
            'Кассир: ' . ($sale['cashier_name'] ?? $sale['user_name'] ?? '—'),
            'Оплата: ' . payment_label((string) $sale['payment_method']),
        ]);
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                $item['name'],
                (int) $item['qty'],
                money((int) $item['price_kop']),
                money((int) $item['total_kop']),
            ];
        }
        self::table($pdf, ['Товар', 'Кол-во', 'Цена', 'Сумма'], $rows, [98, 18, 28, 30], ['L', 'R', 'R', 'R']);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(136, 6, 'Подытог', 0, 0, 'R');
        $pdf->Cell(38, 6, money((int) $sale['total_kop'] + (int) $sale['discount_kop']), 0, 1, 'R');
        if ((int) $sale['discount_kop'] > 0) {
            $pdf->Cell(136, 6, 'Скидка', 0, 0, 'R');
            $pdf->Cell(38, 6, '-' . money((int) $sale['discount_kop']), 0, 1, 'R');
        }
        $pdf->SetFont('DejaVu', 'B', 12);
        $pdf->Cell(136, 8, 'ИТОГО', 0, 0, 'R');
        $pdf->Cell(38, 8, money((int) $sale['total_kop']), 0, 1, 'R');
        if ($sale['received_kop'] !== null) {
            $pdf->SetFont('DejaVu', '', 9);
            $pdf->Cell(136, 5, 'Получено', 0, 0, 'R');
            $pdf->Cell(38, 5, money((int) $sale['received_kop']), 0, 1, 'R');
            $pdf->Cell(136, 5, 'Сдача', 0, 0, 'R');
            $pdf->Cell(38, 5, money((int) $sale['change_kop']), 0, 1, 'R');
        }
        $pdf->Ln(8);
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->SetTextColor(95, 105, 120);
        $pdf->Cell(0, 5, self::txt((string) Settings::get('receipt_footer', 'Спасибо за покупку!')), 0, 1, 'C');
        self::download($pdf, 'receipt-' . (int) $sale['id'] . '.pdf');
    }

    public static function invoice(array $invoice, array $items, array $payments): void
    {
        $pdf = self::pdf();
        self::header($pdf, 'Накладная № ' . $invoice['number'], [
            'Поставщик: ' . ($invoice['supplier_name'] ?? '—'),
            'Дата: ' . date('d.m.Y', strtotime((string) $invoice['invoice_date'])),
            'Срок оплаты: ' . ($invoice['due_date'] ? date('d.m.Y', strtotime((string) $invoice['due_date'])) : '—'),
            'Статус: ' . ($invoice['status'] === 'paid' ? 'оплачена' : ($invoice['status'] === 'partial' ? 'частично оплачена' : 'не оплачена')),
        ]);
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                $item['name'],
                (int) $item['qty'],
                money((int) $item['unit_cost_kop']),
                money((int) $item['total_kop']),
            ];
        }
        self::table($pdf, ['Товар', 'Кол-во', 'Цена', 'Сумма'], $rows, [94, 18, 30, 32], ['L', 'R', 'R', 'R']);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(132, 6, 'Итого', 0, 0, 'R');
        $pdf->Cell(42, 6, money((int) $invoice['total_kop']), 0, 1, 'R');
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->Cell(132, 6, 'Оплачено', 0, 0, 'R');
        $pdf->Cell(42, 6, money((int) $invoice['paid_kop']), 0, 1, 'R');
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(132, 6, 'Долг', 0, 0, 'R');
        $pdf->Cell(42, 6, money((int) $invoice['debt_kop']), 0, 1, 'R');

        if ($payments !== []) {
            $pdf->Ln(6);
            $pdf->SetFont('DejaVu', 'B', 10);
            $pdf->Cell(0, 7, 'Платежи', 0, 1);
            $rows = [];
            foreach ($payments as $payment) {
                $rows[] = [
                    dt_human($payment['paid_at']),
                    payment_label((string) $payment['method']),
                    money((int) $payment['amount_kop']),
                    $payment['user_name'] ?? '—',
                    $payment['comment'] ?? '',
                ];
            }
            self::table($pdf, ['Дата', 'Способ', 'Сумма', 'Сотрудник', 'Комментарий'], $rows, [38, 26, 30, 38, 42], ['L', 'L', 'R', 'L', 'L'], 8);
        }
        self::download($pdf, 'invoice-' . (int) $invoice['id'] . '.pdf');
    }

    public static function inventoryAct(array $session, array $lines): void
    {
        $pdf = self::pdf();
        self::header($pdf, 'Акт инвентаризации ' . $session['number'], [
            'Склад: ' . $session['warehouse'],
            'Начата: ' . dt_human($session['started_at']) . ' · ' . ($session['started_by_name'] ?? '—'),
            'Завершена: ' . ($session['completed_at'] ? dt_human($session['completed_at']) : '—') . ' · ' . ($session['completed_by_name'] ?? '—'),
            'Статус: ' . ($session['status'] === 'completed' ? 'завершена' : 'черновик'),
        ]);
        $rows = [];
        foreach ($lines as $line) {
            $rows[] = [
                $line['name'],
                (int) $line['calculated_qty'],
                (int) ($line['actual_qty'] ?? $line['calculated_qty']),
                (int) $line['diff_qty'] > 0 ? '+' . (int) $line['diff_qty'] : (string) (int) $line['diff_qty'],
                money((int) $line['diff_value_kop']),
            ];
        }
        self::table($pdf, ['Товар', 'Расчёт', 'Факт', 'Разница', 'Сумма'], $rows, [88, 18, 18, 24, 36], ['L', 'R', 'R', 'R', 'R'], 8);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(124, 6, 'Итого расхождение', 0, 0, 'R');
        $pdf->Cell(42, 6, money((int) $session['total_value_kop']), 0, 1, 'R');
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->Ln(8);
        $pdf->Cell(0, 5, self::txt('Недостача / излишок определяется по фактическому количеству. Подписи:'), 0, 1);
        $pdf->Ln(12);
        $pdf->Cell(90, 5, 'МОЛ / кассир: ____________________', 0, 0);
        $pdf->Cell(90, 5, 'Администратор: ____________________', 0, 1);
        self::download($pdf, 'inventory-' . (int) $session['id'] . '.pdf');
    }

    public static function report(string $period, array $summary, array $payments, array $topProducts, array $stock): void
    {
        $pdf = self::pdf();
        self::header($pdf, 'Отчёт за период', [$period]);
        $pdf->SetFont('DejaVu', '', 10);
        $rows = [
            ['Выручка', money((int) $summary['revenue'])],
            ['Себестоимость продаж', money((int) $summary['cost'])],
            ['Расходы (списания)', money((int) $summary['expense'])],
            ['Прибыль', money((int) $summary['profit'])],
            ['Продаж', (string) (int) $summary['count']],
            ['Средний чек', money((int) $summary['avg_check'])],
            ['Скидки', money((int) $summary['discount'])],
        ];
        self::table($pdf, ['Показатель', 'Значение'], $rows, [130, 56], ['L', 'R'], 10);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(0, 7, 'Оплата', 0, 1);
        $rows = [];
        foreach ($payments as $method => $row) {
            $rows[] = [payment_label((string) $method), (int) $row['count'], money((int) $row['revenue'])];
        }
        self::table($pdf, ['Способ', 'Продаж', 'Сумма'], $rows, [90, 30, 56], ['L', 'R', 'R']);
        if ($topProducts !== []) {
            $pdf->SetFont('DejaVu', 'B', 10);
            $pdf->Cell(0, 7, 'Топ товаров', 0, 1);
            $rows = [];
            foreach ($topProducts as $row) {
                $rows[] = [$row['name'], (int) $row['qty'], money((int) $row['revenue'])];
            }
            self::table($pdf, ['Товар', 'Продано', 'Выручка'], $rows, [108, 26, 42], ['L', 'R', 'R'], 8);
        }
        self::download($pdf, 'report-' . date('Y-m-d') . '.pdf');
    }

    public static function shift(array $shift, array $totals, array $stock, array $payments, array $writeoffs): void
    {
        $pdf = self::pdf();
        self::header($pdf, 'Отчёт по смене № ' . $shift['id'], [
            'Открыта: ' . dt_human($shift['opened_at']) . ' · ' . ($shift['opened_by_name'] ?? '—'),
            'Закрыта: ' . ($shift['closed_at'] ? dt_human($shift['closed_at']) : '—') . ' · ' . ($shift['closed_by_name'] ?? '—'),
        ]);
        $rows = [
            ['Продаж', (string) (int) $totals['count']],
            ['Выручка', money((int) $totals['revenue'])],
            ['Себестоимость', money((int) $totals['cost'])],
            ['Расходы', money((int) $totals['expense'])],
            ['Прибыль', money((int) $totals['profit'])],
            ['Остаток единиц', (string) (int) $shift['stock_units']],
        ];
        self::table($pdf, ['Показатель', 'Значение'], $rows, [120, 66], ['L', 'R'], 10);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(0, 7, 'Оплата', 0, 1);
        $rows = [];
        foreach ($payments as $method => $row) {
            $rows[] = [payment_label((string) $method), (int) $row['count'], money((int) $row['revenue'])];
        }
        self::table($pdf, ['Способ', 'Продаж', 'Сумма'], $rows, [90, 30, 56], ['L', 'R', 'R']);
        if ($writeoffs !== []) {
            $pdf->SetFont('DejaVu', 'B', 10);
            $pdf->Cell(0, 7, 'Расходы смены', 0, 1);
            $rows = [];
            foreach ($writeoffs as $row) {
                $rows[] = [$row['product_name'] ?? '—', (int) $row['qty'], $row['employee_name'] ?? '—', money((int) $row['total_kop'])];
            }
            self::table($pdf, ['Товар', 'Кол-во', 'Сотрудник', 'Сумма'], $rows, [80, 20, 45, 30], ['L', 'R', 'L', 'R'], 8);
        }
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(0, 7, 'Остаток на конец смены', 0, 1);
        $rows = [];
        foreach ($stock as $row) {
            $rows[] = [$row['name'], (int) $row['qty'], money((int) $row['unit_cost_kop']), money((int) $row['stock_value_kop'])];
        }
        self::table($pdf, ['Товар', 'Остаток', 'Себестоимость', 'Стоимость'], $rows, [100, 22, 34, 32], ['L', 'R', 'R', 'R'], 8);
        self::download($pdf, 'shift-' . (int) $shift['id'] . '.pdf');
    }
}
