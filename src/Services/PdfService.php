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
        require_once PIZ_ROOT . '/src/Lib/tfpdf/PdfDocument.php';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', PIZ_ROOT . '/src/Lib/tfpdf/font/');
        }
        if (!defined('FPDF_FONT_WRITE_PATH')) {
            define('FPDF_FONT_WRITE_PATH', PIZ_ROOT . '/src/Lib/tfpdf/font/');
        }
        $pdf = new \tFPDF\PdfDocument('P', 'mm', 'A4');
        $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
        $pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->footerLeft = (string) Settings::get('store_name', 'КИОСК ДОНЕР');
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

    private static function amount(int $kopecks): string
    {
        $sign = $kopecks < 0 ? '-' : '';
        return $sign . number_format(abs($kopecks) / 100, 0, ',', ' ') . ' руб.';
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
                self::amount((int) $item['price_kop']),
                self::amount((int) $item['total_kop']),
            ];
        }
        self::table($pdf, ['Товар', 'Кол-во', 'Цена', 'Сумма'], $rows, [98, 18, 28, 30], ['L', 'R', 'R', 'R']);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(136, 6, 'Подытог', 0, 0, 'R');
        $pdf->Cell(38, 6, self::amount((int) $sale['total_kop'] + (int) $sale['discount_kop']), 0, 1, 'R');
        if ((int) $sale['discount_kop'] > 0) {
            $pdf->Cell(136, 6, 'Скидка', 0, 0, 'R');
            $pdf->Cell(38, 6, '-' . self::amount((int) $sale['discount_kop']), 0, 1, 'R');
        }
        $pdf->SetFont('DejaVu', 'B', 12);
        $pdf->Cell(136, 8, 'ИТОГО', 0, 0, 'R');
        $pdf->Cell(38, 8, self::amount((int) $sale['total_kop']), 0, 1, 'R');
        if ($sale['received_kop'] !== null) {
            $pdf->SetFont('DejaVu', '', 9);
            $pdf->Cell(136, 5, 'Получено', 0, 0, 'R');
            $pdf->Cell(38, 5, self::amount((int) $sale['received_kop']), 0, 1, 'R');
            $pdf->Cell(136, 5, 'Сдача', 0, 0, 'R');
            $pdf->Cell(38, 5, self::amount((int) $sale['change_kop']), 0, 1, 'R');
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
                self::amount((int) $item['unit_cost_kop']),
                self::amount((int) $item['total_kop']),
            ];
        }
        self::table($pdf, ['Товар', 'Кол-во', 'Цена', 'Сумма'], $rows, [94, 18, 30, 32], ['L', 'R', 'R', 'R']);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(132, 6, 'Итого', 0, 0, 'R');
        $pdf->Cell(42, 6, self::amount((int) $invoice['total_kop']), 0, 1, 'R');
        $pdf->SetFont('DejaVu', '', 9);
        $pdf->Cell(132, 6, 'Оплачено', 0, 0, 'R');
        $pdf->Cell(42, 6, self::amount((int) $invoice['paid_kop']), 0, 1, 'R');
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(132, 6, 'Долг', 0, 0, 'R');
        $pdf->Cell(42, 6, self::amount((int) $invoice['debt_kop']), 0, 1, 'R');

        if ($payments !== []) {
            $pdf->Ln(6);
            $pdf->SetFont('DejaVu', 'B', 10);
            $pdf->Cell(0, 7, 'Платежи', 0, 1);
            $rows = [];
            foreach ($payments as $payment) {
                $rows[] = [
                    dt_human($payment['paid_at']),
                    payment_label((string) $payment['method']),
                    self::amount((int) $payment['amount_kop']),
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
                self::amount((int) $line['diff_value_kop']),
            ];
        }
        self::table($pdf, ['Товар', 'Расчёт', 'Факт', 'Разница', 'Сумма'], $rows, [88, 18, 18, 24, 36], ['L', 'R', 'R', 'R', 'R'], 8);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(124, 6, 'Итого расхождение', 0, 0, 'R');
        $pdf->Cell(42, 6, self::amount((int) $session['total_value_kop']), 0, 1, 'R');
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
            ['Выручка', self::amount((int) $summary['revenue'])],
            ['Себестоимость продаж', self::amount((int) $summary['cost'])],
            ['Расходы (списания)', self::amount((int) $summary['expense'])],
            ['Прибыль', self::amount((int) $summary['profit'])],
            ['Продаж', (string) (int) $summary['count']],
            ['Средний чек', self::amount((int) $summary['avg_check'])],
            ['Скидки', self::amount((int) $summary['discount'])],
        ];
        self::table($pdf, ['Показатель', 'Значение'], $rows, [130, 56], ['L', 'R'], 10);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->Cell(0, 7, 'Оплата', 0, 1);
        $rows = [];
        foreach ($payments as $method => $row) {
            $rows[] = [payment_label((string) $method), (int) $row['count'], self::amount((int) $row['revenue'])];
        }
        self::table($pdf, ['Способ', 'Продаж', 'Сумма'], $rows, [90, 30, 56], ['L', 'R', 'R']);
        if ($topProducts !== []) {
            $pdf->SetFont('DejaVu', 'B', 10);
            $pdf->Cell(0, 7, 'Топ товаров', 0, 1);
            $rows = [];
            foreach ($topProducts as $row) {
                $rows[] = [$row['name'], (int) $row['qty'], self::amount((int) $row['revenue'])];
            }
            self::table($pdf, ['Товар', 'Продано', 'Выручка'], $rows, [108, 26, 42], ['L', 'R', 'R'], 8);
        }
        self::download($pdf, 'report-' . date('Y-m-d') . '.pdf');
    }

    public static function shift(array $shift, array $totals, array $stock, array $payments, array $writeoffs): void
    {
        $pdf = self::pdf();
        self::shiftHeader($pdf, $shift);

        $opened = strtotime((string) $shift['opened_at']);
        $closed = $shift['closed_at'] ? strtotime((string) $shift['closed_at']) : null;
        $duration = 'идёт';
        if ($closed !== null) {
            $minutes = max(0, (int) round(($closed - $opened) / 60));
            $duration = intdiv($minutes, 60) . ' ч ' . ($minutes % 60) . ' мин';
        }

        $cardWidth = 59.0;
        $cardGap = 4.5;
        $y = 43;
        self::infoCard($pdf, 12, $y, $cardWidth, 'ОТКРЫТА', date('d.m.Y H:i', $opened), (string) ($shift['opened_by_name'] ?? '—'), [242, 107, 33]);
        self::infoCard($pdf, 12 + $cardWidth + $cardGap, $y, $cardWidth, 'ЗАКРЫТА', $closed ? date('d.m.Y H:i', $closed) : '—', (string) ($shift['closed_by_name'] ?? '—'), [35, 157, 103]);
        self::infoCard($pdf, 12 + 2 * ($cardWidth + $cardGap), $y, $cardWidth, 'ДЛИТЕЛЬНОСТЬ', $duration, 'смена № ' . (int) $shift['id'], [59, 130, 246]);

        $y = 70;
        $kpiWidth = 44.25;
        $kpiGap = 3;
        $revenue = (int) $totals['revenue'];
        $count = (int) $totals['count'];
        self::kpiCard($pdf, 12, $y, $kpiWidth, 'ВЫРУЧКА', self::amount($revenue), 'за смену', [242, 107, 33]);
        self::kpiCard($pdf, 12 + $kpiWidth + $kpiGap, $y, $kpiWidth, 'ПРИБЫЛЬ', self::amount((int) $totals['profit']), 'после расходов', [35, 157, 103]);
        self::kpiCard($pdf, 12 + 2 * ($kpiWidth + $kpiGap), $y, $kpiWidth, 'ПРОДАЖ', (string) $count, 'чек' . ($count === 1 ? '' : 'ов'), [59, 130, 246]);
        self::kpiCard($pdf, 12 + 3 * ($kpiWidth + $kpiGap), $y, $kpiWidth, 'СРЕДНИЙ ЧЕК', self::amount($count > 0 ? (int) round($revenue / $count) : 0), 'на чек', [139, 92, 246]);

        $pdf->SetFillColor(247, 248, 251);
        $pdf->Rect(12, 100, 186, 11, 'F');
        $pdf->SetFont('DejaVu', '', 8);
        $pdf->SetTextColor(96, 106, 122);
        $pdf->SetXY(16, 102.5);
        $pdf->Cell(58, 5, 'Себестоимость: ' . self::amount((int) $totals['cost']), 0, 0);
        $pdf->Cell(58, 5, 'Расходы: ' . self::amount((int) $totals['expense']), 0, 0);
        $pdf->Cell(54, 5, 'Остаток: ' . (int) $shift['stock_units'] . ' ед.', 0, 1);

        self::sectionTitle($pdf, 'Оплата', 'По способам оплаты');
        self::paymentTable($pdf, $payments, $revenue);

        if ($writeoffs !== []) {
            self::sectionTitle($pdf, 'Расходы смены', 'Списания и питание сотрудников');
            self::writeoffTable($pdf, $writeoffs);
        }

        self::sectionTitle($pdf, 'Остаток на конец смены', 'Товары и их стоимость');
        self::stockTable($pdf, $stock);

        self::download($pdf, 'shift-' . (int) $shift['id'] . '.pdf');
    }

    private static function shiftHeader(\tFPDF\PDF $pdf, array $shift): void
    {
        $pdf->AddPage();
        $store = (string) Settings::get('store_name', 'КИОСК ДОНЕР');
        $pdf->SetFillColor(19, 25, 37);
        $pdf->Rect(0, 0, 210, 36, 'F');
        $pdf->SetFillColor(242, 107, 33);
        $pdf->Rect(12, 10, 16, 16, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('DejaVu', 'B', 12);
        $pdf->SetXY(12, 13.5);
        $pdf->Cell(16, 7, self::fit(mb_substr($store, 0, 1), 16, 12), 0, 0, 'C');
        $pdf->SetFont('DejaVu', 'B', 15);
        $pdf->SetXY(33, 9.5);
        $pdf->Cell(110, 7, self::fit($store, 110, 15), 0, 1);
        $pdf->SetFont('DejaVu', '', 8.5);
        $pdf->SetTextColor(178, 187, 201);
        $pdf->SetXY(33, 17);
        $pdf->Cell(110, 5, 'Отчёт по смене № ' . (int) $shift['id'], 0, 1);

        $closed = $shift['status'] === 'closed';
        $pdf->SetFillColor($closed ? 35 : 190, $closed ? 157 : 130, $closed ? 103 : 40);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('DejaVu', 'B', 7.5);
        $pdf->SetXY(150, 13.5);
        $pdf->Cell(48, 8, $closed ? 'СМЕНА ЗАКРЫТА' : 'СМЕНА ОТКРЫТА', 0, 0, 'C', true);
        $pdf->SetY(43);
    }

    private static function infoCard(\tFPDF\PDF $pdf, float $x, float $y, float $w, string $label, string $value, string $sub, array $color): void
    {
        $pdf->SetFillColor(247, 248, 251);
        $pdf->Rect($x, $y, $w, 21, 'F');
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect($x, $y, $w, 2, 'F');
        $pdf->SetTextColor(112, 121, 135);
        $pdf->SetFont('DejaVu', 'B', 6.8);
        $pdf->SetXY($x + 4, $y + 4.2);
        $pdf->Cell($w - 8, 4, $label, 0, 1);
        $pdf->SetTextColor(28, 35, 48);
        $pdf->SetFont('DejaVu', 'B', 9.5);
        $pdf->SetXY($x + 4, $y + 9);
        $pdf->Cell($w - 8, 5, self::fit($value, $w - 8, 9.5), 0, 1);
        $pdf->SetTextColor(112, 121, 135);
        $pdf->SetFont('DejaVu', '', 7);
        $pdf->SetXY($x + 4, $y + 15);
        $pdf->Cell($w - 8, 4, self::fit($sub, $w - 8, 7), 0, 1);
    }

    private static function kpiCard(\tFPDF\PDF $pdf, float $x, float $y, float $w, string $label, string $value, string $sub, array $color): void
    {
        $pdf->SetFillColor(247, 248, 251);
        $pdf->Rect($x, $y, $w, 24, 'F');
        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect($x, $y, $w, 2.5, 'F');
        $pdf->SetTextColor(112, 121, 135);
        $pdf->SetFont('DejaVu', 'B', 6.5);
        $pdf->SetXY($x + 4, $y + 4);
        $pdf->Cell($w - 8, 4, $label, 0, 1);
        $pdf->SetTextColor(28, 35, 48);
        $pdf->SetFont('DejaVu', 'B', 10.5);
        $pdf->SetXY($x + 4, $y + 9);
        $pdf->Cell($w - 8, 7, self::fit($value, $w - 8, 10.5), 0, 1);
        $pdf->SetTextColor(112, 121, 135);
        $pdf->SetFont('DejaVu', '', 6.8);
        $pdf->SetXY($x + 4, $y + 17);
        $pdf->Cell($w - 8, 4, $sub, 0, 1);
    }

    private static function sectionTitle(\tFPDF\PDF $pdf, string $title, string $sub = ''): void
    {
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
        }
        $y = $pdf->GetY() + 1;
        $pdf->SetFillColor(242, 107, 33);
        $pdf->Rect(12, $y + 1, 2.5, 6, 'F');
        $pdf->SetTextColor(28, 35, 48);
        $pdf->SetFont('DejaVu', 'B', 10);
        $pdf->SetXY(17, $y);
        $pdf->Cell(0, 6, $title, 0, 1);
        if ($sub !== '') {
            $pdf->SetTextColor(125, 134, 148);
            $pdf->SetFont('DejaVu', '', 7.5);
            $pdf->SetXY(17, $y + 5.5);
            $pdf->Cell(0, 4, $sub, 0, 1);
        }
        $pdf->SetY($y + ($sub !== '' ? 10 : 8));
    }

    private static function paymentTable(\tFPDF\PDF $pdf, array $payments, int $revenue): void
    {
        if ($pdf->GetY() > 255) {
            $pdf->AddPage();
        }
        $x = 12;
        $y = $pdf->GetY();
        $pdf->SetFillColor(28, 35, 48);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('DejaVu', 'B', 7.5);
        $pdf->SetXY($x, $y);
        $pdf->Cell(84, 7, 'Способ', 0, 0, 'L', true);
        $pdf->Cell(25, 7, 'Продаж', 0, 0, 'R', true);
        $pdf->Cell(45, 7, 'Сумма', 0, 0, 'R', true);
        $pdf->Cell(32, 7, 'Доля', 0, 0, 'R', true);
        $pdf->Ln();
        $total = 0;
        $count = 0;
        foreach ($payments as $row) {
            $total += (int) $row['revenue'];
            $count += (int) $row['count'];
        }
        $colors = ['cash' => [242, 107, 33], 'card' => [59, 130, 246], 'transfer' => [139, 92, 246]];
        $i = 0;
        foreach ($payments as $method => $row) {
            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
                $pdf->SetFillColor(28, 35, 48);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('DejaVu', 'B', 7.5);
                $pdf->SetXY(12, 12);
                $pdf->Cell(84, 7, 'Способ', 0, 0, 'L', true);
                $pdf->Cell(25, 7, 'Продаж', 0, 0, 'R', true);
                $pdf->Cell(45, 7, 'Сумма', 0, 0, 'R', true);
                $pdf->Cell(32, 7, 'Доля', 0, 0, 'R', true);
                $pdf->Ln();
            }
            $pdf->SetFillColor($i % 2 === 0 ? 248 : 255, $i % 2 === 0 ? 249 : 255, $i % 2 === 0 ? 252 : 255);
            $pdf->SetTextColor(35, 43, 57);
            $pdf->SetFont('DejaVu', '', 8);
            $y = $pdf->GetY();
            $pdf->SetXY(12, $y);
            $pdf->Cell(84, 7, '', 0, 0, 'L', true);
            $color = $colors[$method] ?? [125, 134, 148];
            $pdf->SetFillColor($color[0], $color[1], $color[2]);
            $pdf->Rect(15, $y + 2.5, 2.2, 2.2, 'F');
            $pdf->SetXY(20, $y);
            $pdf->Cell(76, 7, self::fit(payment_label((string) $method), 76, 8), 0, 0, 'L');
            $share = $revenue > 0 ? round((int) $row['revenue'] / $revenue * 100) : 0;
            $pdf->Cell(25, 7, (int) $row['count'], 0, 0, 'R');
            $pdf->Cell(45, 7, self::amount((int) $row['revenue']), 0, 0, 'R');
            $pdf->Cell(32, 7, $share . '%', 0, 1, 'R');
            $i++;
        }
        $pdf->SetFillColor(240, 243, 248);
        $pdf->SetTextColor(28, 35, 48);
        $pdf->SetFont('DejaVu', 'B', 8);
        $pdf->Cell(109, 7, 'Итого', 0, 0, 'L', true);
        $pdf->Cell(25, 7, (string) $count, 0, 0, 'R', true);
        $pdf->Cell(45, 7, self::amount($total), 0, 1, 'R', true);
        $pdf->Ln(4);
    }

    private static function writeoffTable(\tFPDF\PDF $pdf, array $writeoffs): void
    {
        if ($pdf->GetY() > 245) {
            $pdf->AddPage();
        }
        $pdf->SetFillColor(28, 35, 48);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('DejaVu', 'B', 7.5);
        $pdf->Cell(76, 7, 'Товар', 0, 0, 'L', true);
        $pdf->Cell(18, 7, 'Кол-во', 0, 0, 'R', true);
        $pdf->Cell(48, 7, 'Сотрудник', 0, 0, 'L', true);
        $pdf->Cell(44, 7, 'Сумма', 0, 0, 'R', true);
        $pdf->Ln();
        $i = 0;
        $total = 0;
        foreach ($writeoffs as $row) {
            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
                $pdf->SetFillColor(28, 35, 48);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('DejaVu', 'B', 7.5);
                $pdf->Cell(76, 7, 'Товар', 0, 0, 'L', true);
                $pdf->Cell(18, 7, 'Кол-во', 0, 0, 'R', true);
                $pdf->Cell(48, 7, 'Сотрудник', 0, 0, 'L', true);
                $pdf->Cell(44, 7, 'Сумма', 0, 0, 'R', true);
                $pdf->Ln();
            }
            $pdf->SetFillColor($i % 2 === 0 ? 248 : 255, $i % 2 === 0 ? 249 : 255, $i % 2 === 0 ? 252 : 255);
            $pdf->SetTextColor(35, 43, 57);
            $pdf->SetFont('DejaVu', '', 7.8);
            $pdf->Cell(76, 6.5, self::fit((string) ($row['product_name'] ?? '—'), 76, 7.8), 0, 0, 'L', true);
            $pdf->Cell(18, 6.5, (string) (int) $row['qty'], 0, 0, 'R');
            $pdf->Cell(48, 6.5, self::fit((string) ($row['employee_name'] ?? '—'), 48, 7.8), 0, 0, 'L');
            $pdf->Cell(44, 6.5, self::amount((int) $row['total_kop']), 0, 1, 'R');
            $total += (int) $row['total_kop'];
            $i++;
        }
        $pdf->SetFillColor(253, 242, 242);
        $pdf->SetTextColor(180, 60, 60);
        $pdf->SetFont('DejaVu', 'B', 8);
        $pdf->Cell(142, 6.5, 'Итого расходов', 0, 0, 'L', true);
        $pdf->Cell(44, 6.5, self::amount($total), 0, 1, 'R', true);
        $pdf->Ln(4);
    }

    private static function stockTable(\tFPDF\PDF $pdf, array $stock): void
    {
        if ($stock === []) {
            return;
        }
        $head = function () use ($pdf): void {
            $pdf->SetFillColor(28, 35, 48);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('DejaVu', 'B', 7.5);
            $pdf->Cell(84, 7, 'Товар', 0, 0, 'L', true);
            $pdf->Cell(24, 7, 'Остаток', 0, 0, 'R', true);
            $pdf->Cell(38, 7, 'Себестоимость', 0, 0, 'R', true);
            $pdf->Cell(40, 7, 'Стоимость', 0, 0, 'R', true);
            $pdf->Ln();
        };
        if ($pdf->GetY() > 245) {
            $pdf->AddPage();
        }
        $head();
        $i = 0;
        $totalQty = 0;
        $totalValue = 0;
        foreach ($stock as $row) {
            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
                $pdf->SetFont('DejaVu', 'B', 8);
                $pdf->SetTextColor(110, 119, 133);
                $pdf->Cell(0, 6, 'Остаток на конец смены (продолжение)', 0, 1);
                $head();
            }
            $pdf->SetFillColor($i % 2 === 0 ? 248 : 255, $i % 2 === 0 ? 249 : 255, $i % 2 === 0 ? 252 : 255);
            $pdf->SetTextColor(35, 43, 57);
            $pdf->SetFont('DejaVu', '', 7.8);
            $pdf->Cell(84, 6.2, self::fit((string) $row['name'], 84, 7.8), 0, 0, 'L', true);
            $pdf->Cell(24, 6.2, (string) (int) $row['qty'], 0, 0, 'R');
            $pdf->Cell(38, 6.2, self::amount((int) $row['unit_cost_kop']), 0, 0, 'R');
            $pdf->Cell(40, 6.2, self::amount((int) $row['stock_value_kop']), 0, 1, 'R');
            $totalQty += (int) $row['qty'];
            $totalValue += (int) $row['stock_value_kop'];
            $i++;
        }
        $pdf->SetFillColor(240, 243, 248);
        $pdf->SetTextColor(28, 35, 48);
        $pdf->SetFont('DejaVu', 'B', 8);
        $pdf->Cell(84, 7, 'Итого', 0, 0, 'L', true);
        $pdf->Cell(24, 7, (string) $totalQty, 0, 0, 'R', true);
        $pdf->Cell(38, 7, '', 0, 0, 'R', true);
        $pdf->Cell(40, 7, self::amount($totalValue), 0, 1, 'R', true);
    }
}

