
<?php
$periodChips = [
    'today' => 'Сегодня',
    '7d' => '7 дней',
    'month' => 'Месяц',
    '3m' => '3 месяца',
    '6m' => '6 месяцев',
];
$revenueTrend = percent_change((int) $summary['revenue'], (int) $previous['revenue']);
$profitTrend = percent_change((int) $summary['profit'], (int) $previous['profit']);
$countTrend = percent_change((int) $summary['count'], (int) $previous['count']);
?>
    <div class="toolbar report-toolbar">
        <div class="chips report-chips">
            <?php foreach ($periodChips as $key => $label): ?>
                <a class="chip <?= $period['period'] === $key ? 'is-active' : '' ?>" data-period="<?= e($key) ?>"
                   href="<?= e(query_url('/', ['period' => $key, 'from' => null, 'to' => null])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <button type="button" class="btn btn--ghost btn--sm report-date-trigger" data-modal-open="report-date-modal"><?= icon('calendar') ?><span class="report-date-trigger__text">Даты</span></button>
        </div>
        <form method="get" action="<?= e(url('/')) ?>" class="report-date-form" style="gap: 6px;">
            <input type="hidden" name="period" value="custom">
            <input class="input" type="date" name="from" value="<?= e($period['from']) ?>" style="height: 32px; width: auto; font-size: 12px;">
            <span class="muted">—</span>
            <input class="input" type="date" name="to" value="<?= e($period['to']) ?>" style="height: 32px; width: auto; font-size: 12px;">
            <button class="btn btn--ghost btn--sm" type="submit">Применить</button>
        </form>
        <div class="toolbar__right report-exports">
            <a class="btn btn--ghost btn--sm" href="<?= e(query_url('/reports/export', ['type' => 'daily'])) ?>"><?= icon('download') ?> Дни</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(query_url('/reports/export', ['type' => 'products'])) ?>"><?= icon('download') ?> Товары</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(query_url('/reports/export', ['type' => 'stock'])) ?>"><?= icon('download') ?> Склад</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(pdf_url('/reports/pdf', ['period' => $period['period'], 'from' => $period['from'], 'to' => $period['to']])) ?>"><?= icon('download') ?> Скачать PDF</a>
            <details class="report-export-more">
                <summary class="btn btn--ghost btn--sm"><?= icon('download') ?> Ещё</summary>
                <div class="report-export-more__menu">
                    <a href="<?= e(query_url('/reports/export', ['type' => 'daily'])) ?>"><?= icon('download') ?> Дни</a>
                    <a href="<?= e(query_url('/reports/export', ['type' => 'products'])) ?>"><?= icon('download') ?> Товары</a>
                    <a href="<?= e(query_url('/reports/export', ['type' => 'stock'])) ?>"><?= icon('download') ?> Склад</a>
                    <a href="<?= e(pdf_url('/reports/pdf', ['period' => $period['period'], 'from' => $period['from'], 'to' => $period['to']])) ?>"><?= icon('download') ?> Скачать PDF</a>
                </div>
            </details>
        </div>
    </div>

    <div class="modal" id="report-date-modal" aria-hidden="true">
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="report-date-title">
            <div class="modal__head"><div><h3 id="report-date-title">Свой период</h3><p>Выберите даты и нажмите «Применить»</p></div><button type="button" class="icon-btn" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button></div>
            <form method="get" action="<?= e(url('/')) ?>">
                <input type="hidden" name="period" value="custom">
                <div class="modal__body"><div class="form-grid form-grid--2"><label class="field"><span class="label">С</span><input class="input" type="date" name="from" value="<?= e($period['from']) ?>" required></label><label class="field"><span class="label">По</span><input class="input" type="date" name="to" value="<?= e($period['to']) ?>" required></label></div></div>
                <div class="modal__foot"><button class="btn btn--ghost" type="button" data-modal-close>Отмена</button><button class="btn btn--primary" type="submit">Применить</button></div>
            </form>
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

    <section class="card" data-report-views>
        <header class="card__head">
            <div>
                <h3>Динамика по дням</h3>
                <p><?= e(period_label($period['from'], $period['to'])) ?> · <?= (int) $days ?> <?= plural((int) $days, 'день', 'дня', 'дней') ?></p>
            </div>
            <div class="report-view-tools">
                <div class="report-view-switch" role="tablist" aria-label="Вид динамики">
                    <button type="button" class="is-active" data-report-view="chart" role="tab" aria-selected="true">График</button>
                    <button type="button" data-report-view="lines" role="tab" aria-selected="false">Линии</button>
                    <button type="button" data-report-view="table" role="tab" aria-selected="false">Таблица</button>
                    <button type="button" data-report-view="list" role="tab" aria-selected="false">Список</button>
                </div>
                <div class="legend">
                    <span class="legend__item"><i class="legend__dot legend__dot--orange"></i>Выручка</span>
                    <span class="legend__item"><i class="legend__dot legend__dot--green"></i>Прибыль</span>
                </div>
            </div>
        </header>
        <div data-report-panel="chart">
            <?php \PIZ\View::partial('partials/bar-chart', ['series' => $series, 'height' => 150, 'showProfit' => true]); ?>
        </div>
        <div data-report-panel="lines" hidden>
            <?php \PIZ\View::partial('partials/line-chart', ['series' => $series, 'height' => 150]); ?>
        </div>
        <div data-report-panel="table" hidden>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead><tr><th>Дата</th><th class="num">Продаж</th><th class="num">Выручка</th><th class="num">Расходы</th><th class="num">Прибыль</th></tr></thead>
                    <tbody>
                    <?php foreach ($series as $date => $row): ?>
                        <tr>
                            <td><?= e(date('d.m.Y', strtotime((string) $date))) ?></td>
                            <td class="num"><?= (int) $row['count'] ?></td>
                            <td class="num strong"><?= e(money((int) $row['revenue'])) ?></td>
                            <td class="num" style="color: var(--red);"><?= e(money((int) ($row['expense'] ?? 0))) ?></td>
                            <td class="num" style="color: var(--green); font-weight: 650;"><?= e(money((int) $row['profit'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div data-report-panel="list" hidden>
            <div class="report-list">
                <?php foreach ($series as $date => $row): ?>
                    <div class="report-list__row">
                        <div><strong><?= e(date('d.m.Y', strtotime((string) $date))) ?></strong><span><?= (int) $row['count'] ?> продаж</span></div>
                        <div class="report-list__values"><span>Выручка <strong><?= e(money((int) $row['revenue'])) ?></strong></span><span>Прибыль <strong style="color:var(--green);"><?= e(money((int) $row['profit'])) ?></strong></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
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
