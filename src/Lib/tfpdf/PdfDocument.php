<?php
declare(strict_types=1);

namespace tFPDF;

/**
 * PDF с фирменным колонтитулом и нумерацией страниц.
 */
class PdfDocument extends PDF
{
    public string $footerLeft = '';

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(226, 230, 237);
        $this->SetLineWidth(0.2);
        $this->Line(12, $this->GetY(), 198, $this->GetY());
        $this->SetY(-10);
        $this->SetFont('DejaVu', '', 7.5);
        $this->SetTextColor(132, 140, 152);
        $this->Cell(0, 5, $this->footerLeft . '  ·  стр. ' . $this->PageNo(), 0, 0, 'C');
    }
}
