<div class="page">
    <div class="toolbar">
        <div class="toolbar__left"><a class="btn btn--ghost btn--sm" href="<?= e(url('/suppliers/invoices')) ?>"><?= icon('chevron-left', 'icon icon--xs') ?> Накладные</a><div><h2 style="font-size: 20px;">Накладная № <?= e($invoice['number']) ?></h2><div class="muted" style="font-size: 12px;"><?= e($invoice['supplier_name'] ?? '—') ?> · <?= e(date('d.m.Y', strtotime((string) $invoice['invoice_date']))) ?></div></div></div>
        <div class="toolbar__right"><a class="btn btn--primary btn--sm" href="<?= e(url('/suppliers/invoices/' . $invoice['id'] . '/pdf')) ?>"><?= icon('download') ?> Скачать PDF</a><a class="btn btn--ghost btn--sm" href="<?= e(url('/suppliers/' . $invoice['supplier_id'])) ?>">Поставщик</a></div>
    </div>

    <section class="summary-grid">
        <div class="stat"><span class="stat__label">Сумма накладной</span><span class="stat__value"><?= e(money((int) $invoice['total_kop'])) ?></span></div>
        <div class="stat"><span class="stat__label">Оплачено</span><span class="stat__value stat__value--green"><?= e(money((int) $invoice['paid_kop'])) ?></span></div>
        <div class="stat"><span class="stat__label">Долг</span><span class="stat__value stat__value--red"><?= e(money((int) $invoice['debt_kop'])) ?></span></div>
        <div class="stat"><span class="stat__label">Статус</span><span class="stat__value" style="font-size:15px;"><?= invoice_status_badge((string) $invoice['status']) ?></span></div>
    </section>

    <section class="card">
        <header class="card__head"><div><h3>Позиции накладной</h3><p><?= e($invoice['supplier_name'] ?? '') ?><?= $invoice['due_date'] ? ' · оплатить до ' . date('d.m.Y', strtotime((string) $invoice['due_date'])) : '' ?></p></div></header>
        <div class="table-wrap"><table class="table"><thead><tr><th>Товар</th><th class="num">Кол-во</th><th class="num">Цена</th><th class="num">Сумма</th></tr></thead><tbody>
            <?php foreach ($items as $item): ?><tr><td class="strong"><?= e($item['name']) ?></td><td class="num"><?= (int) $item['qty'] ?> <?= e($item['unit'] ?? '') ?></td><td class="num"><?= e(money((int) $item['unit_cost_kop'])) ?></td><td class="num strong"><?= e(money((int) $item['total_kop'])) ?></td></tr><?php endforeach; ?>
        </tbody><tfoot><tr><th colspan="3" class="num">Итого</th><th class="num"><?= e(money((int) $invoice['total_kop'])) ?></th></tr></tfoot></table></div>
    </section>

    <?php if ((int) $invoice['debt_kop'] > 0): ?>
    <section class="card">
        <header class="card__head"><div><h3>Оплатить поставщику</h3><p>Платёж уменьшит долг по накладной</p></div></header>
        <form method="post" action="<?= e(url('/suppliers/invoices/' . $invoice['id'] . '/pay')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid form-grid--3">
                <label class="field"><span class="label">Сумма, ₽</span><input class="input" type="text" name="amount" inputmode="decimal" value="<?= e(kop_to_input((int) $invoice['debt_kop'])) ?>" required></label>
                <label class="field"><span class="label">Способ</span><select class="select" name="method"><option value="cash">Наличные</option><option value="card">Карта</option><option value="transfer">Перевод</option></select></label>
                <label class="field"><span class="label">Комментарий</span><input class="input" type="text" name="comment" maxlength="255" placeholder="Оплата накладной"></label>
            </div>
            <div class="form-actions"><button class="btn btn--primary" type="submit"><?= icon('check') ?> Сохранить платёж</button></div>
        </form>
    </section>
    <?php endif; ?>

    <section class="card">
        <header class="card__head"><div><h3>Платежи</h3><p>История оплат по накладной</p></div></header>
        <?php if ($payments === []): ?><div class="empty empty--compact"><?= icon('wallet') ?><p>Платежей пока нет</p></div><?php else: ?>
        <div class="table-wrap"><table class="table table--compact"><thead><tr><th>Дата</th><th>Способ</th><th class="num">Сумма</th><th>Сотрудник</th><th>Комментарий</th></tr></thead><tbody>
            <?php foreach ($payments as $p): ?><tr><td class="muted"><?= e(dt_human($p['paid_at'])) ?></td><td><?= payment_badge((string) $p['method']) ?></td><td class="num"><?= e(money((int) $p['amount_kop'])) ?></td><td><?= e($p['user_name'] ?? '—') ?></td><td class="muted"><?= e($p['comment'] ?? '') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
</div>
