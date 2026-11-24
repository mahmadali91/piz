<?php
/** @var array $shift */
$paymentsTotal = array_sum(array_column($payments, 'revenue'));
?>
<div class="page">
    <div class="toolbar">
        <div class="toolbar__left">
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/shift')) ?>"><?= icon('chevron-left', 'icon icon--xs') ?> К смене</a>
            <div>
                <h2 style="font-size: 20px;">Смена № <?= (int) $shift['id'] ?></h2>
                <div class="muted" style="font-size: 12px; margin-top: 2px;"><?= e(dt_human($shift['opened_at'])) ?> — <?= e(dt_human($shift['closed_at'])) ?></div>
            </div>
        </div>
        <div class="toolbar__right">
            <a class="btn btn--primary btn--sm" href="<?= e(url('/shifts/' . $shift['id'] . '/export')) ?>"><?= icon('download') ?> Скачать CSV</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(pdf_url('/shifts/' . $shift['id'] . '/pdf')) ?>"><?= icon('download') ?> Скачать PDF</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/shifts')) ?>">История</a>
        </div>
    </div>

    <section class="summary-grid">
        <div class="stat"><span class="stat__label">Продаж</span><span class="stat__value"><?= (int) $shift['sales_count'] ?></span></div>
        <div class="stat"><span class="stat__label">Выручка</span><span class="stat__value"><?= e(money((int) $shift['revenue_kop'])) ?></span></div>
        <?php if ($finance): ?>
            <div class="stat"><span class="stat__label">Себестоимость</span><span class="stat__value"><?= e(money((int) $shift['cost_kop'])) ?></span></div>
            <div class="stat"><span class="stat__label">Расходы</span><span class="stat__value stat__value--red"><?= e(money((int) $shift['expense_kop'])) ?></span></div>
            <div class="stat"><span class="stat__label">Прибыль</span><span class="stat__value stat__value--green"><?= e(money((int) $shift['profit_kop'])) ?></span></div>
        <?php endif; ?>
        <div class="stat"><span class="stat__label">Остаток единиц</span><span class="stat__value"><?= (int) $shift['stock_units'] ?></span></div>
    </section>

    <div class="dash-grid">
        <section class="card" style="grid-column: span 5;">
            <header class="card__head"><div><h3>Оплата</h3><p>Выручка по способам</p></div></header>
            <div class="pay-breakdown">
                <?php foreach ($payments as $method => $row):
                    $share = $paymentsTotal > 0 ? round($row['revenue'] / $paymentsTotal * 100) : 0;
                    ?>
                    <div class="pay-row">
                        <div class="pay-row__head">
                            <span class="pay-row__name"><?= icon(payment_icon($method), 'icon icon--xs') ?> <?= e(payment_label($method)) ?></span>
                            <span class="pay-row__sum"><?= e(money((int) $row['revenue'])) ?></span>
                        </div>
                        <div class="bar"><i class="bar__fill bar__fill--<?= e($method) ?>" style="width: <?= $share ?>%"></i></div>
                        <div class="pay-row__meta"><?= (int) $row['count'] ?> <?= plural((int) $row['count'], 'продажа', 'продажи', 'продаж') ?> · <?= $share ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card" style="grid-column: span 7;">
            <header class="card__head"><div><h3>Информация о смене</h3><p>Кто открыл и закрыл</p></div></header>
            <div class="health-list">
                <div class="health-row"><span>Открыл</span><strong><?= e($shift['opened_by_name'] ?? '—') ?></strong></div>
                <div class="health-row"><span>Открыта</span><strong><?= e(dt_human($shift['opened_at'])) ?></strong></div>
                <div class="health-row"><span>Закрыл</span><strong><?= e($shift['closed_by_name'] ?? '—') ?></strong></div>
                <div class="health-row"><span>Закрыта</span><strong><?= e(dt_human($shift['closed_at'])) ?></strong></div>
                <?php if (!empty($shift['note'])): ?><div class="health-row"><span>Комментарий</span><strong><?= e($shift['note']) ?></strong></div><?php endif; ?>
            </div>
        </section>
    </div>

    <?php if ($writeoffs !== []): ?>
        <section class="card">
            <header class="card__head"><div><h3>Расходы смены</h3><p>Списания, не входящие в выручку</p></div></header>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead><tr><th>Время</th><th>Товар</th><th class="num">Кол-во</th><th>Сотрудник</th><th>Причина</th><?php if ($finance): ?><th class="num">Сумма</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($writeoffs as $row): ?>
                        <tr>
                            <td class="muted"><?= e(time_human($row['created_at'])) ?></td>
                            <td><?= e($row['product_name'] ?? '—') ?></td>
                            <td class="num"><?= (int) $row['qty'] ?></td>
                            <td><?= e($row['employee_name'] ?: '—') ?></td>
                            <td><?= e($row['reason']) ?></td>
                            <?php if ($finance): ?><td class="num" style="color: var(--red);"><?= e(money((int) $row['total_kop'])) ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <header class="card__head">
            <div><h3>Остаток на конец смены</h3><p>Зафиксирован при закрытии · <?= count($stock) ?> позиций</p></div>
            <span class="badge badge--gray"><?= (int) $stockTotals['units'] ?> ед.</span>
        </header>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead>
                <tr>
                    <th>Товар</th><th>Артикул</th><th class="num">Остаток</th>
                    <?php if ($finance): ?><th class="num">Себестоимость</th><th class="num">Цена</th><th class="num">Стоимость</th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stock as $row): ?>
                    <tr>
                        <td class="strong"><?= e($row['name']) ?></td>
                        <td class="muted"><?= e($row['sku'] ?: '—') ?></td>
                        <td class="num"><span class="stock-pill <?= (int) $row['qty'] <= 0 ? 'stock-pill--out' : '' ?>"><?= (int) $row['qty'] ?></span></td>
                        <?php if ($finance): ?>
                            <td class="num"><?= e(money((int) $row['unit_cost_kop'])) ?></td>
                            <td class="num"><?= e(money((int) $row['sale_price_kop'])) ?></td>
                            <td class="num"><?= e(money((int) $row['stock_value_kop'])) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($finance): ?>
            <div class="form-section" style="display: flex; justify-content: flex-end; gap: 12px;">
                <span class="muted">Итого по закупке:</span>
                <strong><?= e(money((int) $stockTotals['value'])) ?></strong>
            </div>
        <?php endif; ?>
    </section>
</div>
