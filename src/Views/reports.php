<?php
$periodChips = [
    'today' => 'Сегодня',
    '7d' => '7 дней',
    '30d' => '30 дней',
    'month' => 'Этот месяц',
    'all' => 'Всё время',
];
$revenueTrend = percent_change((int) $summary['revenue'], (int) $previous['revenue']);
$profitTrend = percent_change((int) $summary['profit'], (int) $previous['profit']);
$countTrend = percent_change((int) $summary['count'], (int) $previous['count']);
?>
<div class="page">
    <div class="toolbar">
        <div class="chips">
            <?php foreach ($periodChips as $key => $label): ?>
                <a class="chip <?= $period['period'] === $key ? 'is-active' : '' ?>"
                   href="<?= e(query_url('/reports', ['period' => $key, 'from' => null, 'to' => null])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <form method="get" action="<?= e(url('/reports')) ?>" class="toolbar__right" style="gap: 6px;">
                <input type="hidden" name="period" value="custom">
                <input class="input" type="date" name="from" value="<?= e($period['from']) ?>" style="height: 32px; width: auto; font-size: 12px;">
                <span class="muted">—</span>
                <input class="input" type="date" name="to" value="<?= e($period['to']) ?>" style="height: 32px; width: auto; font-size: 12px;">
                <button class="btn btn--ghost btn--sm" type="submit">Применить</button>
            </form>
        </div>
        <div class="toolbar__right">
            <a class="btn btn--ghost btn--sm" href="<?= e(query_url('/reports/export', ['type' => 'daily'])) ?>"><?= icon('download') ?> Дни</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(query_url('/reports/export', ['type' => 'products'])) ?>"><?= icon('download') ?> Товары</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(query_url('/reports/export', ['type' => 'stock'])) ?>"><?= icon('download') ?> Склад</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(pdf_url('/reports/pdf', ['period' => $period['period'], 'from' => $period['from'], 'to' => $period['to']])) ?>"><?= icon('download') ?> Скачать PDF</a>
        </div>
    </div>

    <section class="summary-grid">
        <div class="stat stat--accent">
            <span class="stat__label">Выручка за период</span>
            <span class="stat__value"><?= e(money($summary['revenue'])) ?></span>
            <span class="muted" style="font-size: 11px;"><?= trend_badge($revenueTrend) ?> к прошлому периоду</span>
        </div>
        <div class="stat">
            <span class="stat__label">Прибыль</span>
            <span class="stat__value stat__value--green"><?= e(money($summary['profit'])) ?></span>
            <span class="muted" style="font-size: 11px;"><?= trend_badge($profitTrend) ?> к прошлому периоду</span>
        </div>
        <div class="stat">
            <span class="stat__label">Маржинальность</span>
            <span class="stat__value"><?= e(rtrim(rtrim(number_format($margin, 1, ',', ' '), '0'), ',')) ?>%</span>
        </div>
        <div class="stat">
            <span class="stat__label">Продаж</span>
            <span class="stat__value"><?= (int) $summary['count'] ?></span>
            <span class="muted" style="font-size: 11px;"><?= trend_badge($countTrend) ?> к прошлому периоду</span>
        </div>
        <div class="stat">
            <span class="stat__label">Средний чек</span>
            <span class="stat__value"><?= e(money($summary['avg_check'])) ?></span>
        </div>
        <div class="stat">
            <span class="stat__label">Расходы (списания)</span>
            <span class="stat__value stat__value--red"><?= e(money((int) $summary['expense'])) ?></span>
        </div>
        <div class="stat">
            <span class="stat__label">Скидки</span>
            <span class="stat__value"><?= e(money($summary['discount'])) ?></span>
        </div>
        <?php if ($canceled > 0): ?>
            <div class="stat">
                <span class="stat__label">Отменено чеков</span>
                <span class="stat__value stat__value--red"><?= (int) $canceled ?></span>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <header class="card__head">
            <div>
                <h3>Динамика по дням</h3>
                <p><?= e(period_label($period['from'], $period['to'])) ?> · <?= (int) $days ?> <?= plural((int) $days, 'день', 'дня', 'дней') ?></p>
            </div>
            <div class="legend">
                <span class="legend__item"><i class="legend__dot legend__dot--orange"></i>Выручка</span>
                <span class="legend__item"><i class="legend__dot legend__dot--green"></i>Прибыль</span>
            </div>
        </header>
        <?php \PIZ\View::partial('partials/bar-chart', ['series' => $series, 'height' => 290, 'showProfit' => true]); ?>
    </section>

    <div class="dash-grid">
        <section class="card" style="grid-column: span 4;">
            <header class="card__head">
                <div>
                    <h3>Способы оплаты</h3>
                    <p>Структура выручки</p>
                </div>
            </header>
            <div class="pay-breakdown">
                <?php foreach ($payments as $method => $row):
                    $share = $paymentsTotal > 0 ? (int) round($row['revenue'] / $paymentsTotal * 100) : 0;
                    ?>
                    <div class="pay-row">
                        <div class="pay-row__head">
                            <span class="pay-row__name"><?= icon(payment_icon($method), 'icon icon--xs') ?> <?= e(payment_label($method)) ?></span>
                            <span class="pay-row__sum"><?= e(money($row['revenue'])) ?></span>
                        </div>
                        <div class="bar"><i class="bar__fill bar__fill--<?= e($method) ?>" style="width: <?= $share ?>%"></i></div>
                        <div class="pay-row__meta"><?= (int) $row['count'] ?> <?= plural((int) $row['count'], 'продажа', 'продажи', 'продаж') ?> · <?= $share ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 18px; padding-top: 14px; border-top: 1px dashed var(--line);">
                <div class="cart-line"><span>Всего выручки</span><strong><?= e(money($paymentsTotal)) ?></strong></div>
            </div>
        </section>

        <section class="card" style="grid-column: span 8;">
            <header class="card__head">
                <div>
                    <h3>Продажи по товарам</h3>
                    <p>Топ-20 по выручке за период</p>
                </div>
                <?= icon('package', 'icon icon--muted') ?>
            </header>
            <?php if ($topProducts === []): ?>
                <div class="empty empty--compact"><?= icon('chart') ?><p>Нет данных за период</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table table--compact">
                        <thead>
                        <tr>
                            <th>Товар</th>
                            <th class="num">Продано</th>
                            <th class="num">Выручка</th>
                            <th class="num">Прибыль</th>
                            <th class="num">Доля</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($topProducts as $row):
                            $share = $summary['revenue'] > 0 ? round((int) $row['revenue'] / $summary['revenue'] * 100, 1) : 0;
                            ?>
                            <tr>
                                <td class="strong"><?= e($row['name']) ?></td>
                                <td class="num"><?= (int) $row['qty'] ?></td>
                                <td class="num strong"><?= e(money((int) $row['revenue'])) ?></td>
                                <td class="num" style="color: var(--green);"><?= e(money((int) $row['revenue'] - (int) $row['cost'])) ?></td>
                                <td class="num muted"><?= e(number_format($share, 1, ',', ' ')) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="card">
        <header class="card__head">
            <div>
                <h3>Склад на текущий момент</h3>
                <p>Остатки и их стоимость</p>
            </div>
            <a class="btn btn--ghost btn--sm" href="<?= e(query_url('/reports/export', ['type' => 'stock'])) ?>"><?= icon('download') ?> CSV</a>
        </header>
        <div class="summary-grid" style="margin-bottom: 14px;">
            <div class="stat">
                <span class="stat__label">Позиций</span>
                <span class="stat__value"><?= (int) $stock['total'] ?></span>
            </div>
            <div class="stat">
                <span class="stat__label">Единиц</span>
                <span class="stat__value"><?= (int) $stock['units'] ?></span>
            </div>
            <div class="stat">
                <span class="stat__label">По закупке</span>
                <span class="stat__value"><?= e(money($stock['cost_value'])) ?></span>
            </div>
            <div class="stat">
                <span class="stat__label">По продаже</span>
                <span class="stat__value stat__value--green"><?= e(money($stock['retail_value'])) ?></span>
            </div>
        </div>

        <?php if ($lowStock === []): ?>
            <div class="empty empty--compact"><?= icon('check') ?><p>Все товары в достатке</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Категория</th>
                        <th class="num">Остаток</th>
                        <th class="num">Минимум</th>
                        <th class="num">Стоимость</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lowStock as $product): ?>
                        <tr>
                            <td class="strong"><?= e($product['name']) ?></td>
                            <td class="muted"><?= e($product['category']) ?></td>
                            <td class="num">
                                <span class="stock-pill <?= (int) $product['stock_qty'] <= 0 ? 'stock-pill--out' : 'stock-pill--low' ?>"><?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?></span>
                            </td>
                            <td class="num muted"><?= (int) $product['low_stock'] ?></td>
                            <td class="num"><?= e(money((int) $product['stock_qty'] * (int) $product['purchase_price_kop'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
