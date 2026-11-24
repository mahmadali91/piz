<?php $debt = array_sum(array_map(static fn ($i) => (int) $i['debt_kop'], $invoices)); ?>
<div class="page">
    <div class="toolbar">
        <div class="toolbar__left">
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/suppliers')) ?>"><?= icon('chevron-left', 'icon icon--xs') ?> Поставщики</a>
            <div><h2 style="font-size: 20px;"><?= e($supplier['name']) ?></h2><div class="muted" style="font-size: 12px;"><?= e($supplier['contact'] ?: 'Контакт не указан') ?></div></div>
        </div>
        <div class="toolbar__right">
            <a class="btn btn--primary btn--sm" href="<?= e(url('/suppliers/invoices/create')) ?>"><?= icon('plus') ?> Новая накладная</a>
            <button class="btn btn--ghost btn--sm" type="button" data-supplier-edit='<?= e(json_encode($supplier, JSON_UNESCAPED_UNICODE)) ?>'><?= icon('edit') ?> Изменить</button>
        </div>
    </div>

    <section class="summary-grid">
        <div class="stat"><span class="stat__label">Накладных</span><span class="stat__value"><?= count($invoices) ?></span></div>
        <div class="stat"><span class="stat__label">Долг поставщику</span><span class="stat__value stat__value--red"><?= e(money((int) $debt)) ?></span></div>
        <div class="stat"><span class="stat__label">Телефон</span><span class="stat__value" style="font-size: 15px;"><?= e($supplier['phone'] ?: '—') ?></span></div>
        <div class="stat"><span class="stat__label">ИНН</span><span class="stat__value" style="font-size: 15px;"><?= e($supplier['inn'] ?: '—') ?></span></div>
    </section>

    <section class="card">
        <header class="card__head"><div><h3>Накладные</h3><p>Цены закупки, оплаты и остаток долга</p></div></header>
        <?php if ($invoices === []): ?>
            <div class="empty"><?= icon('receipt') ?><p>Накладных пока нет</p><a class="btn btn--soft btn--sm" href="<?= e(url('/suppliers/invoices/create')) ?>">Создать накладную</a></div>
        <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Накладная</th><th>Дата</th><th>Срок</th><th class="num">Сумма</th><th class="num">Оплачено</th><th class="num">Долг</th><th>Статус</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><a class="number" href="<?= e(url('/suppliers/invoices/' . $invoice['id'])) ?>"><?= e($invoice['number']) ?></a></td>
                        <td class="muted"><?= e(date('d.m.Y', strtotime((string) $invoice['invoice_date']))) ?></td>
                        <td class="muted"><?= e($invoice['due_date'] ? date('d.m.Y', strtotime((string) $invoice['due_date'])) : '—') ?></td>
                        <td class="num"><?= e(money((int) $invoice['total_kop'])) ?></td>
                        <td class="num"><?= e(money((int) $invoice['paid_kop'])) ?></td>
                        <td class="num <?= (int) $invoice['debt_kop'] > 0 ? 'stat__value--red' : '' ?>"><?= e(money((int) $invoice['debt_kop'])) ?></td>
                        <td><?= invoice_status_badge((string) $invoice['status']) ?></td>
                        <td><div class="row-actions"><a class="icon-btn" href="<?= e(url('/suppliers/invoices/' . $invoice['id'])) ?>"><?= icon('eye') ?></a><a class="icon-btn" href="<?= e(url('/suppliers/invoices/' . $invoice['id'] . '/pdf')) ?>"><?= icon('download') ?></a></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <?php if (!empty($supplier['note'])): ?><section class="card"><p class="muted"><?= e($supplier['note']) ?></p></section><?php endif; ?>
</div>
