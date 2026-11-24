<?php
/** @var array $user */
$report = $reportData ?? [];
$summary = $report['summary'] ?? $today;
$periodLabel = !empty($report['period'])
    ? period_label($report['period']['from'], $report['period']['to'])
    : 'сегодня';
?>
<div class="page">
    <section class="welcome">
        <div class="welcome__text">
            <span class="eyebrow"><?= e(date('d.m.Y')) ?> · <?= e(\PIZ\Settings::get('store_name', 'КИОСК ДОНЕР')) ?></span>
            <h2><?= e(greeting()) ?>, <?= e(explode(' ', (string) $user['name'])[0]) ?>!</h2>
            <p>
                За период <strong><?= e($periodLabel) ?></strong>:
                <?= (int) $summary['count'] ?> <?= plural((int) $summary['count'], 'продажа', 'продажи', 'продаж') ?>
                на <?= e(money((int) $summary['revenue'])) ?>.
                <?php if (($report['canceled'] ?? 0) > 0): ?>
                    <span class="welcome__note"><?= icon('alert', 'icon icon--xs') ?> Отменено: <?= (int) $report['canceled'] ?>.</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="welcome__actions">
            <?php if (\PIZ\Auth::can('pos.use')): ?>
                <a class="btn btn--primary" href="<?= e(url('/pos')) ?>"><?= icon('cart') ?> Открыть кассу</a>
            <?php endif; ?>
            <?php if (\PIZ\Auth::can('stock.manage')): ?>
                <a class="btn btn--soft" href="<?= e(url('/stock/in')) ?>"><?= icon('truck') ?> Оформить приход</a>
            <?php endif; ?>
            <?php if (\PIZ\Auth::can('shift.manage')): ?>
                <a class="btn btn--soft" href="<?= e(url('/shift')) ?>"><?= icon('clock') ?> <?= !empty($currentShift) ? 'Смена открыта' : 'Открыть смену' ?></a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($canReports && $report !== []): ?>
        <section class="report-merged">
            <div class="report-merged__head">
                <div>
                    <span class="eyebrow">аналитика</span>
                    <h2>Показатели за период</h2>
                    <p>Один фильтр для всех отчётов и графиков</p>
                </div>
            </div>
            <?php (static function (array $data): void {
                extract($data, EXTR_SKIP);
                require PIZ_ROOT . '/src/Views/partials/reports-content.php';
            })($report); ?>
        </section>
    <?php else: ?>
        <section class="summary-grid">
            <div class="stat stat--accent"><span class="stat__label">Выручка сегодня</span><span class="stat__value"><?= e(money((int) $today['revenue'])) ?></span></div>
            <?php if ($finance): ?><div class="stat"><span class="stat__label">Прибыль сегодня</span><span class="stat__value stat__value--green"><?= e(money((int) $today['profit'])) ?></span></div><?php endif; ?>
            <div class="stat"><span class="stat__label">Продаж сегодня</span><span class="stat__value"><?= (int) $today['count'] ?></span></div>
            <div class="stat"><span class="stat__label">Средний чек</span><span class="stat__value"><?= e(money((int) $today['avg_check'])) ?></span></div>
        </section>
    <?php endif; ?>

    <?php if ($recent !== [] || $lowStock !== []): ?>
        <div class="dash-grid">
            <section class="card dash-grid__recent">
                <header class="card__head">
                    <div><h3>Последние продажи</h3><p>Свежие чеки кассы</p></div>
                    <a class="link" href="<?= e(url('/sales')) ?>">Все продажи <?= icon('arrow-right', 'icon icon--xs') ?></a>
                </header>
                <?php if ($recent === []): ?>
                    <div class="empty empty--compact"><?= icon('receipt') ?><p>Продаж пока нет</p></div>
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
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card dash-grid__low">
                <header class="card__head">
                    <div><h3>Пора докупить</h3><p>Остаток ниже минимума</p></div>
                    <?php if ($stock['low_count'] > 0): ?><span class="badge badge--amber"><?= (int) $stock['low_count'] ?></span><?php endif; ?>
                </header>
                <?php if ($lowStock === []): ?>
                    <div class="empty empty--compact"><?= icon('check') ?><p>Все товары в достатке</p></div>
                <?php else: ?>
                    <div class="low-list">
                        <?php foreach ($lowStock as $product): ?>
                            <div class="low-item">
                                <?= product_photo($product, 'thumb thumb--sm') ?>
                                <div class="low-item__main">
                                    <strong><?= e($product['name']) ?></strong>
                                    <span>минимум <?= (int) $product['low_stock'] ?> <?= e($product['unit']) ?></span>
                                </div>
                                <span class="stock-pill <?= (int) $product['stock_qty'] <= 0 ? 'stock-pill--out' : 'stock-pill--low' ?>"><?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</div>
