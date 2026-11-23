<?php /** @var array $user */ ?>
<div class="page">
    <section class="welcome">
        <div class="welcome__text">
            <span class="eyebrow"><?= e(date('d.m.Y')) ?> · <?= e(\PIZ\Settings::get('store_name', 'Киоск PIZ')) ?></span>
            <h2><?= e(greeting()) ?>, <?= e(explode(' ', (string) $user['name'])[0]) ?>!</h2>
            <p>
                <?php if ($today['count'] > 0): ?>
                    Сегодня уже <?= (int) $today['count'] ?> <?= plural((int) $today['count'], 'продажа', 'продажи', 'продаж') ?> на <?= e(money($today['revenue'])) ?>.
                <?php else: ?>
                    Продаж сегодня ещё нет. Самое время открыть кассу.
                <?php endif; ?>
                <?php if ($canceledToday > 0): ?>
                    <span class="welcome__note"><?= icon('alert', 'icon icon--xs') ?> Отменено сегодня: <?= $canceledToday ?>.</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="welcome__actions">
            <?php if (\PIZ\Auth::can('pos.use')): ?>
                <a class="btn btn--primary" href="<?= e(url('/pos')) ?>"><?= icon('cart') ?> Открыть кассу</a>
            <?php endif; ?>
            <?php if (\PIZ\Auth::can('stock.manage')): ?>
                <a class="btn btn--soft" href="<?= e(url('/products')) ?>#stock"><?= icon('truck') ?> Оформить приход</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="kpi-grid">
        <article class="kpi">
            <div class="kpi__top">
                <span class="kpi__icon kpi__icon--orange"><?= icon('wallet') ?></span>
                <?= trend_badge($trends['revenue']) ?>
            </div>
            <div class="kpi__value"><?= e(money($today['revenue'])) ?></div>
            <div class="kpi__label">Выручка сегодня <span>к вчера</span></div>
        </article>

        <?php if ($finance): ?>
            <article class="kpi">
                <div class="kpi__top">
                    <span class="kpi__icon kpi__icon--green"><?= icon('chart') ?></span>
                    <?= trend_badge($trends['profit']) ?>
                </div>
                <div class="kpi__value"><?= e(money($today['profit'])) ?></div>
                <div class="kpi__label">
                    Прибыль сегодня
                    <span><?= $today['revenue'] > 0 ? 'маржа ' . round($today['profit'] / $today['revenue'] * 100) . '%' : 'к вчера' ?></span>
                </div>
            </article>
        <?php else: ?>
            <article class="kpi">
                <div class="kpi__top">
                    <span class="kpi__icon kpi__icon--green"><?= icon('box') ?></span>
                </div>
                <div class="kpi__value"><?= (int) $stock['units'] ?></div>
                <div class="kpi__label">Единиц на складе <span><?= (int) $stock['total'] ?> позиций</span></div>
            </article>
        <?php endif; ?>

        <article class="kpi">
            <div class="kpi__top">
                <span class="kpi__icon kpi__icon--blue"><?= icon('receipt') ?></span>
                <?= trend_badge($trends['count']) ?>
            </div>
            <div class="kpi__value"><?= (int) $today['count'] ?></div>
            <div class="kpi__label">Продаж сегодня <span>к вчера</span></div>
        </article>

        <article class="kpi">
            <div class="kpi__top">
                <span class="kpi__icon kpi__icon--violet"><?= icon('tag') ?></span>
                <?= trend_badge($trends['avg_check']) ?>
            </div>
            <div class="kpi__value"><?= e(money($today['avg_check'])) ?></div>
            <div class="kpi__label">Средний чек <span>к вчера</span></div>
        </article>
    </section>

    <section class="mini-stats">
        <div class="mini-stat">
            <span class="mini-stat__label"><?= icon('package') ?> Позиций в продаже</span>
            <strong><?= (int) $stock['total'] ?></strong>
        </div>
        <div class="mini-stat">
            <span class="mini-stat__label"><?= icon('box') ?> Единиц на складе</span>
            <strong><?= (int) $stock['units'] ?></strong>
        </div>
        <div class="mini-stat <?= $stock['low_count'] > 0 ? 'mini-stat--warn' : '' ?>">
            <span class="mini-stat__label"><?= icon('alert') ?> Заканчивается</span>
            <strong><?= (int) $stock['low_count'] ?></strong>
        </div>
        <?php if ($finance): ?>
            <div class="mini-stat">
                <span class="mini-stat__label"><?= icon('wallet') ?> Остаток по закупке</span>
                <strong><?= e(money($stock['cost_value'])) ?></strong>
            </div>
            <div class="mini-stat">
                <span class="mini-stat__label"><?= icon('sparkles') ?> Потенциальная выручка</span>
                <strong><?= e(money($stock['retail_value'])) ?></strong>
            </div>
        <?php endif; ?>
    </section>

    <div class="dash-grid">
        <section class="card card--chart dash-grid__chart">
            <header class="card__head">
                <div>
                    <h3>Выручка и прибыль</h3>
                    <p>Последние 14 дней</p>
                </div>
                <?php if ($finance): ?>
                    <div class="legend">
                        <span class="legend__item"><i class="legend__dot legend__dot--orange"></i>Выручка</span>
                        <span class="legend__item"><i class="legend__dot legend__dot--green"></i>Прибыль</span>
                    </div>
                <?php endif; ?>
            </header>
            <?php \PIZ\View::partial('partials/bar-chart', ['series' => $series, 'height' => 250, 'showProfit' => $finance]); ?>
        </section>

        <section class="card">
            <header class="card__head">
                <div>
                    <h3>Оплата сегодня</h3>
                    <p><?= $today['count'] > 0 ? e(money($paymentsTotal)) . ' по ' . (int) $today['count'] . ' ' . plural((int) $today['count'], 'чеку', 'чекам', 'чекам') : 'Нет продаж' ?></p>
                </div>
                <?= icon('card', 'icon icon--muted') ?>
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
        </section>

        <section class="card dash-grid__recent">
            <header class="card__head">
                <div>
                    <h3>Последние продажи</h3>
                    <p>Свежие чеки кассы</p>
                </div>
                <a class="link" href="<?= e(url('/sales')) ?>">Все продажи <?= icon('arrow-right', 'icon icon--xs') ?></a>
            </header>
            <?php if ($recent === []): ?>
                <div class="empty">
                    <?= icon('receipt') ?>
                    <p>Продаж пока нет</p>
                    <a class="btn btn--soft btn--sm" href="<?= e(url('/pos')) ?>">Открыть кассу</a>
                </div>
            <?php else: ?>
                <div class="rows">
                    <?php foreach ($recent as $sale): ?>
                        <a class="row-link" href="<?= e(url('/sales/' . $sale['id'])) ?>">
                            <span class="row-link__main">
                                <strong>Чек <?= e($sale['number']) ?></strong>
                                <span><?= e(time_human($sale['created_at'])) ?> · <?= e($sale['cashier_name'] ?? '—') ?> · <?= (int) $sale['items_count'] ?> поз.</span>
                            </span>
                            <span class="row-link__side">
                                <?= payment_badge((string) $sale['payment_method']) ?>
                                <strong><?= e(money((int) $sale['total_kop'])) ?></strong>
                                <?php if ($sale['status'] === 'canceled'): ?><?= sale_status_badge('canceled') ?><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card dash-grid__low">
            <header class="card__head">
                <div>
                    <h3>Пора докупить</h3>
                    <p>Остаток ниже минимума</p>
                </div>
                <?php if ($stock['low_count'] > 0): ?>
                    <span class="badge badge--amber"><?= (int) $stock['low_count'] ?></span>
                <?php endif; ?>
            </header>
            <?php if ($lowStock === []): ?>
                <div class="empty empty--compact">
                    <?= icon('check') ?>
                    <p>Все товары в достатке</p>
                </div>
            <?php else: ?>
                <div class="low-list">
                    <?php foreach ($lowStock as $product): ?>
                        <div class="low-item">
                            <?= product_photo($product, 'thumb thumb--sm') ?>
                            <div class="low-item__main">
                                <strong><?= e($product['name']) ?></strong>
                                <span>минимум <?= (int) $product['low_stock'] ?> <?= e($product['unit']) ?></span>
                            </div>
                            <span class="stock-pill <?= (int) $product['stock_qty'] <= 0 ? 'stock-pill--out' : 'stock-pill--low' ?>">
                                <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a class="card__foot-link" href="<?= e(url('/products?low=1')) ?>">Показать все <?= icon('arrow-right', 'icon icon--xs') ?></a>
            <?php endif; ?>
        </section>

        <section class="card dash-grid__top">
            <header class="card__head">
                <div>
                    <h3>Топ товаров за 7 дней</h3>
                    <p>По выручке</p>
                </div>
                <a class="link" href="<?= e(url('/reports?period=7d')) ?>">Отчёт <?= icon('arrow-right', 'icon icon--xs') ?></a>
            </header>
            <?php if ($topProducts === []): ?>
                <div class="empty empty--compact">
                    <?= icon('sparkles') ?>
                    <p>Данных пока нет</p>
                </div>
            <?php else: ?>
                <div class="top-list">
                    <?php foreach ($topProducts as $index => $item): ?>
                        <div class="top-item">
                            <span class="top-item__rank"><?= $index + 1 ?></span>
                            <div class="top-item__main">
                                <strong><?= e($item['name']) ?></strong>
                                <span><?= (int) $item['qty'] ?> шт · <?= e(money((int) $item['revenue'])) ?></span>
                            </div>
                            <?php if ($finance): ?>
                                <span class="top-item__profit">+<?= e(money((int) $item['revenue'] - (int) $item['cost'])) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
