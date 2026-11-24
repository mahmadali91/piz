<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Models\Product;
use PIZ\Models\Supplier;
use PIZ\Services\PdfService;
use PIZ\View;

final class SupplierController
{
    public function index(): void
    {
        Auth::requirePermission('suppliers.manage');
        View::render('suppliers/index', [
            'title' => 'Поставщики',
            'active' => 'suppliers',
            'user' => Auth::user(),
            'suppliers' => Supplier::all(),
            'invoices' => Supplier::allInvoices(12),
            'debt' => Supplier::debtTotal(),
            'finance' => Auth::can('finance.view'),
        ]);
    }

    public function save(): void
    {
        Auth::requirePermission('suppliers.manage');
        $name = trim((string) ($_POST['name'] ?? ''));
        if (mb_strlen($name) < 2) {
            flash('error', 'Укажите название поставщика.');
            redirect('/suppliers');
        }
        try {
            $id = Supplier::save([
                'name' => mb_substr($name, 0, 160),
                'contact' => mb_substr(trim((string) ($_POST['contact'] ?? '')), 0, 120),
                'phone' => mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 40),
                'email' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 120),
                'address' => mb_substr(trim((string) ($_POST['address'] ?? '')), 0, 255),
                'inn' => mb_substr(trim((string) ($_POST['inn'] ?? '')), 0, 20),
                'note' => mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500),
                'active' => isset($_POST['active']),
            ], (int) ($_POST['id'] ?? 0) ?: null);
            Audit::log('supplier.save', ['id' => $id, 'name' => $name]);
            flash('success', 'Поставщик сохранён.');
        } catch (\Throwable $e) {
            flash('error', 'Не удалось сохранить поставщика: ' . $e->getMessage());
        }
        redirect('/suppliers');
    }

    public function show(string $id): void
    {
        Auth::requirePermission('suppliers.manage');
        $supplier = Supplier::find((int) $id);
        if (!$supplier) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Поставщик не найден'], 'layout');
            return;
        }
        View::render('suppliers/show', [
            'title' => $supplier['name'],
            'active' => 'suppliers',
            'user' => Auth::user(),
            'supplier' => $supplier,
            'invoices' => Supplier::invoices((int) $supplier['id']),
            'finance' => Auth::can('finance.view'),
        ]);
    }

    public function invoices(): void
    {
        Auth::requirePermission('suppliers.manage');
        View::render('suppliers/invoices', [
            'title' => 'Накладные',
            'active' => 'suppliers',
            'user' => Auth::user(),
            'invoices' => Supplier::allInvoices(200),
            'debt' => Supplier::debtTotal(),
            'finance' => Auth::can('finance.view'),
        ]);
    }

    public function createInvoiceForm(): void
    {
        Auth::requirePermission('suppliers.manage');
        View::render('suppliers/invoice-create', [
            'title' => 'Новая накладная',
            'active' => 'suppliers',
            'user' => Auth::user(),
            'suppliers' => Supplier::all(),
            'products' => Product::all(),
            'finance' => Auth::can('finance.view'),
        ]);
    }

    public function storeInvoice(): void
    {
        Auth::requirePermission('suppliers.manage');
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $supplier = Supplier::find($supplierId);
        if (!$supplier) {
            flash('error', 'Выберите поставщика.');
            redirect('/suppliers/invoices/create');
        }
        $number = trim((string) ($_POST['number'] ?? ''));
        if ($number === '') {
            $number = 'INV-' . date('Ymd-His');
        }
        $date = (string) ($_POST['invoice_date'] ?? date('Y-m-d'));
        $due = trim((string) ($_POST['due_date'] ?? ''));
        if (!strtotime($date)) {
            $date = date('Y-m-d');
        }
        if ($due !== '' && !strtotime($due)) {
            $due = '';
        }
        $items = [];
        $productIds = $_POST['product_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $costs = $_POST['unit_cost'] ?? [];
        if (is_array($productIds)) {
            foreach ($productIds as $index => $productId) {
                $qty = (int) ($qtys[$index] ?? 0);
                $cost = parse_money($costs[$index] ?? '');
                if ((int) $productId <= 0 || $qty <= 0 || $cost === null) {
                    continue;
                }
                $items[] = ['product_id' => (int) $productId, 'qty' => $qty, 'unit_cost_kop' => max(0, $cost)];
            }
        }
        if ($items === []) {
            flash('error', 'Добавьте хотя бы одну позицию с количеством и ценой.');
            redirect('/suppliers/invoices/create');
        }
        try {
            $invoiceId = Supplier::createInvoice([
                'supplier_id' => $supplierId,
                'number' => mb_substr($number, 0, 64),
                'invoice_date' => date('Y-m-d', strtotime($date)),
                'due_date' => $due !== '' ? date('Y-m-d', strtotime($due)) : '',
                'comment' => mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 255),
            ], $items, Auth::id());

            foreach ($items as $item) {
                Product::addStock(
                    (int) $item['product_id'],
                    (int) $item['qty'],
                    (int) $item['unit_cost_kop'],
                    'Накладная № ' . $number,
                    Auth::id(),
                    'Поставщик: ' . $supplier['name']
                );
            }
            Audit::log('supplier.invoice', ['id' => $invoiceId, 'supplier' => $supplier['name'], 'total' => array_sum(array_map(static fn ($i) => $i['qty'] * $i['unit_cost_kop'], $items))]);
            flash('success', 'Накладная проведена, товар оприходован.');
            redirect('/suppliers/invoices/' . $invoiceId);
        } catch (\Throwable $e) {
            flash('error', 'Не удалось провести накладную: ' . $e->getMessage());
            redirect('/suppliers/invoices/create');
        }
    }

    public function invoice(string $id): void
    {
        Auth::requirePermission('suppliers.manage');
        $invoice = Supplier::invoice((int) $id);
        if (!$invoice) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Накладная не найдена'], 'layout');
            return;
        }
        View::render('suppliers/invoice', [
            'title' => 'Накладная № ' . $invoice['number'],
            'active' => 'suppliers',
            'user' => Auth::user(),
            'invoice' => $invoice,
            'items' => Supplier::invoiceItems((int) $invoice['id']),
            'payments' => Supplier::payments((int) $invoice['id']),
            'finance' => Auth::can('finance.view'),
        ]);
    }

    public function pay(string $id): void
    {
        Auth::requirePermission('suppliers.manage');
        $invoice = Supplier::invoice((int) $id);
        if (!$invoice) {
            flash('error', 'Накладная не найдена.');
            redirect('/suppliers/invoices');
        }
        $amount = parse_money($_POST['amount'] ?? '');
        if ($amount === null) {
            flash('error', 'Укажите сумму платежа.');
            redirect('/suppliers/invoices/' . $invoice['id']);
        }
        try {
            Supplier::addPayment((int) $invoice['id'], $amount, (string) ($_POST['method'] ?? 'cash'), trim((string) ($_POST['comment'] ?? '')), Auth::id());
            Audit::log('supplier.payment', ['invoice' => (int) $invoice['id'], 'amount' => $amount]);
            flash('success', 'Платёж поставщику сохранён.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/suppliers/invoices/' . $invoice['id']);
    }

    public function pdf(string $id): void
    {
        Auth::requirePermission('suppliers.manage');
        $invoice = Supplier::invoice((int) $id);
        if (!$invoice) {
            http_response_code(404);
            return;
        }
        PdfService::invoice($invoice, Supplier::invoiceItems((int) $invoice['id']), Supplier::payments((int) $invoice['id']));
    }
}
