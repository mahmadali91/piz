<div class="page">
    <div class="toolbar">
        <div><h2 style="font-size: 22px;">Накладные</h2><p class="muted" style="font-size: 12.5px; margin-top: 3px;">Закупочные цены и долг поставщикам</p></div>
        <div class="toolbar__right"><a class="btn btn--ghost btn--sm" href="<?= e(url('/suppliers')) ?>">Поставщики</a><a class="btn btn--primary btn--sm" href="<?= e(url('/suppliers/invoices/create')) ?>"><?= icon('plus') ?> Новая накладная</a></div>
    </div>
    <section class="summary-grid"><div class="stat"><span class="stat__label">Накладных</span><span class="stat__value"><?= count($invoices) ?></span></div><div class="stat"><span class="stat__label">Общий долг</span><span class="stat__value stat__value--red"><?= e(money((int) $debt)) ?></span></div></section>
    <section class="card">
        <?php if ($invoices === []): ?><div class="empty"><?= icon('receipt') ?><p>Накладных пока нет</p></div><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Номер</th><th>Поставщик</th><th>Дата</th><th class="num">Сумма</th><th class="num">Оплачено</th><th class="num">Долг</th><th>Статус</th><th></th></tr></thead>
            <tbody><?php foreach ($invoices as $invoice): ?>
                <tr><td><a class="number" href="<?= e(url('/suppliers/invoices/' . $invoice['id'])) ?>"><?= e($invoice['number']) ?></a></td><td><?= e($invoice['supplier_name'] ?? '—') ?></td><td class="muted"><?= e(date('d.m.Y', strtotime((string) $invoice['invoice_date']))) ?></td><td class="num"><?= e(money((int) $invoice['total_kop'])) ?></td><td class="num"><?= e(money((int) $invoice['paid_kop'])) ?></td><td class="num"><?= e(money((int) $invoice['debt_kop'])) ?></td><td><?= invoice_status_badge((string) $invoice['status']) ?></td><td><div class="row-actions"><a class="icon-btn" href="<?= e(url('/suppliers/invoices/' . $invoice['id'])) ?>"><?= icon('eye') ?></a><a class="icon-btn" href="<?= e(pdf_url('/suppliers/invoices/' . $invoice['id'] . '/pdf')) ?>"><?= icon('download') ?></a></div></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </section>
</div>
