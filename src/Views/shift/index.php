<?php
/** @var array|null $shift */
$paymentsTotal = $totals ? (int) $totals['revenue'] : 0;
?>
<div class="page">
    <div class="toolbar">
        <div>
            <h2 style="font-size: 22px;">Смена</h2>
            <p class="muted" style="font-size: 12.5px; margin-top: 3px;">Открытие, закрытие и отчёт по остаткам</p>
        </div>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/shifts')) ?>"><?= icon('list') ?> История смен</a>
    </div>

    <?php if (!$shift): ?>
        <section class="card">
            <div class="empty" style="padding: 44px 20px;">
                <?= icon('clock') ?>
                <h3 style="margin-top: 4px;">Смена не открыта</h3>
                <p>Откройте смену перед началом работы. Продажа и списание откроют её автоматически, но лучше сделать это явно.</p>
                <form method="post" action="<?= e(url('/shift/open')) ?>" style="margin-top: 14px;">
                    <?= csrf_field() ?>
                    <button class="btn btn--primary" type="submit"><?= icon('plus') ?> Открыть смену</button>
                </form>
            </div>
        </section>
    <?php else: ?>
        <section class="card">
            <div class="toolbar">
                <div class="toolbar__left">
                    <span class="badge badge--green">Смена открыта</span>
                    <span class="muted" style="font-size: 12.5px;"><?= e(dt_human($shift['opened_at'])) ?> · <?= e($shift['opened_by_name'] ?? '—') ?></span>
                </div>
                <div class="toolbar__right">
                    <span class="muted" style="font-size: 12px;">№ <?= (int) $shift['id'] ?></span>
                </div>
            </div>
        </section>

        <section class="summary-grid">
            <div class="stat"><span class="stat__label">Продаж</span><span class="stat__value"><?= (int) $totals['count'] ?></span></div>
            <div class="stat"><span class="stat__label">Выручка</span><span class="stat__value"><?= e(money((int) $totals['revenue'])) ?></span></div>
            <?php if ($finance): ?>
                <div class="stat"><span class="stat__label">Расходы (списания)</span><span class="stat__value stat__value--red"><?= e(money((int) $totals['expense'])) ?></span></div>
                <div class="stat"><span class="stat__label">Прибыль</span><span class="stat__value stat__value--green"><?= e(money((int) $totals['profit'])) ?></span></div>
            <?php endif; ?>
            <div class="stat"><span class="stat__label">Остаток единиц</span><span class="stat__value"><?= (int) $stock['units'] ?></span></div>
        </section>

        <div class="dash-grid">
            <section class="card" style="grid-column: span 7;">
                <header class="card__head"><div><h3>Оплата в смене</h3><p>По способам оплаты</p></div></header>
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

            <section class="card" style="grid-column: span 5;">
                <header class="card__head"><div><h3>Закрыть смену</h3><p>Остатки попадут в отчёт</p></div><?= icon('clock', 'icon icon--muted') ?></header>
                <?php if ($canClose): ?>
                    <form method="post" action="<?= e(url('/shift/close')) ?>" data-confirm="Закрыть смену и сохранить отчёт по остаткам?">
                        <?= csrf_field() ?>
                        <div class="stack">
                            <label class="field">
                                <span class="label">Комментарий к смене</span>
                                <input class="input" type="text" name="note" maxlength="255" placeholder="Необязательно">
                            </label>
                            <button class="btn btn--primary btn--block" type="submit"><?= icon('check') ?> Закрыть смену и сформировать отчёт</button>
                        </div>
                    </form>
                    <p class="hint" style="margin-top: 10px;">После закрытия откроется отчёт с остатком на конец смены и кнопкой скачивания CSV.</p>
                <?php else: ?>
                    <div class="alert alert--warn"><?= icon('alert') ?><span>Смену открыл другой сотрудник. Закрыть её может он или администратор.</span></div>
                <?php endif; ?>
            </section>
        </div>

        <?php if ($writeoffs !== []): ?>
            <section class="card">
                <header class="card__head"><div><h3>Расходы в смене</h3><p>Списания товара</p></div></header>
                <div class="table-wrap">
                    <table class="table table--compact">
                        <thead><tr><th>Когда</th><th>Товар</th><th class="num">Кол-во</th><th>Сотрудник</th><th>Причина</th><?php if ($finance): ?><th class="num">Сумма</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($writeoffs as $row): ?>
                            <tr>
                                <td class="muted"><?= e(time_human($row['created_at'])) ?></td>
                                <td><?= e($row['product_name'] ?? '—') ?></td>
                                <td class="num"><?= (int) $row['qty'] ?></td>
                                <td><?= e($row['employee_name'] ?: '—') ?></td>
                                <td><span class="badge badge--red"><?= e($row['reason']) ?></span></td>
                                <?php if ($finance): ?><td class="num" style="color: var(--red);"><?= e(money((int) $row['total_kop'])) ?></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <section class="card">
        <header class="card__head">
            <div><h3>История смен</h3><p>Открытие, закрытие и итоги последних смен</p></div>
            <a class="link" href="<?= e(url('/shifts')) ?>">Вся история (<?= (int) $historyTotal ?>) <?= icon('arrow-right', 'icon icon--xs') ?></a>
        </header>
        <?php if ($recentShifts === []): ?>
            <div class="empty empty--compact"><?= icon('clock') ?><p>Смен пока нет</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead><tr><th>Смена</th><th>Открыта</th><th>Закрыта</th><th>Открыл</th><th class="num">Продаж</th><th class="num">Выручка</th><?php if ($finance): ?><th class="num">Прибыль</th><?php endif; ?><th class="num">Остаток</th><th>Статус</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recentShifts as $item): ?>
                        <tr>
                            <td><a class="number" href="<?= e(url($item['status'] === 'open' ? '/shift' : '/shifts/' . $item['id'])) ?>">№ <?= (int) $item['id'] ?></a></td>
                            <td class="muted" style="white-space:nowrap;"><?= e(dt_human($item['opened_at'])) ?></td>
                            <td class="muted" style="white-space:nowrap;"><?= e($item['closed_at'] ? dt_human($item['closed_at']) : '—') ?></td>
                            <td><?= e($item['opened_by_name'] ?? '—') ?></td>
                            <td class="num"><?= $item['status'] === 'closed' ? (int) $item['sales_count'] : '—' ?></td>
                            <td class="num"><?= $item['status'] === 'closed' ? e(money((int) $item['revenue_kop'])) : '—' ?></td>
                            <?php if ($finance): ?><td class="num" style="color:var(--green);font-weight:650;"><?= $item['status'] === 'closed' ? e(money((int) $item['profit_kop'])) : '—' ?></td><?php endif; ?>
                            <td class="num"><?= $item['status'] === 'closed' ? (int) $item['stock_units'] : '—' ?></td>
                            <td><?= $item['status'] === 'open' ? '<span class="badge badge--green">Открыта</span>' : '<span class="badge badge--gray">Закрыта</span>' ?></td>
                            <td><div class="row-actions"><a class="icon-btn" href="<?= e(url($item['status'] === 'open' ? '/shift' : '/shifts/' . $item['id'])) ?>"><?= icon('eye') ?></a><?php if ($item['status'] === 'closed'): ?><a class="icon-btn" href="<?= e(url('/shifts/' . $item['id'] . '/pdf')) ?>" title="Скачать PDF"><?= icon('download') ?></a><?php endif; ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
