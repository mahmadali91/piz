<?php
/** @var array $summary */
$periodChips = [
    'today' => 'Сегодня',
    '7d' => '7 дней',
    '30d' => '30 дней',
    'month' => 'Этот месяц',
    'all' => 'Всё время',
];
$query = $_GET;
unset($query['page']);
?>
<div class="page">
    <section class="summary-grid">
        <div class="stat stat--accent">
            <span class="stat__label">Выручка за период</span>
            <span class="stat__value"><?= e(money($summary['revenue'])) ?></span>
        </div>
        <?php if ($finance): ?>
            <div class="stat">
                <span class="stat__label">Прибыль</span>
                <span class="stat__value stat__value--green"><?= e(money($summary['profit'])) ?></span>
            </div>
        <?php endif; ?>
        <div class="stat">
            <span class="stat__label">Продаж</span>
            <span class="stat__value"><?= (int) $summary['completed'] ?></span>
        </div>
        <div class="stat">
            <span class="stat__label">Средний чек</span>
            <span class="stat__value"><?= $summary['completed'] > 0 ? e(money((int) round($summary['revenue'] / $summary['completed']))) : '—' ?></span>
        </div>
        <?php if ($finance): ?>
            <div class="stat">
                <span class="stat__label">Скидки</span>
                <span class="stat__value"><?= e(money($summary['discount'])) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($summary['canceled'] > 0): ?>
            <div class="stat">
                <span class="stat__label">Отменено</span>
                <span class="stat__value stat__value--red"><?= (int) $summary['canceled'] ?></span>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="toolbar" style="margin-bottom: 14px;">
            <div class="toolbar__left">
                <div class="chips">
                    <?php foreach ($periodChips as $key => $label): ?>
                        <a class="chip <?= $period['period'] === $key ? 'is-active' : '' ?>"
                           href="<?= e(query_url('/sales', ['period' => $key, 'from' => null, 'to' => null, 'page' => null])) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="toolbar__right">
                <a class="btn btn--ghost btn--sm" href="<?= e(query_url('/sales/export', $query)) ?>"><?= icon('download') ?> Экспорт</a>
            </div>
        </div>

        <form method="get" action="<?= e(url('/sales')) ?>" class="toolbar" style="margin-bottom: 12px;">
            <div class="toolbar__left">
                <div class="search-box">
                    <?= icon('search') ?>
                    <input class="input" type="search" name="q" value="<?= e($filters['search']) ?>" placeholder="Номер чека или покупатель">
                </div>
                <select class="select" name="payment">
                    <option value="">Все оплаты</option>
                    <option value="cash" <?= $filters['payment'] === 'cash' ? 'selected' : '' ?>>Наличные</option>
                    <option value="card" <?= $filters['payment'] === 'card' ? 'selected' : '' ?>>Карта</option>
                    <option value="transfer" <?= $filters['payment'] === 'transfer' ? 'selected' : '' ?>>Перевод</option>
                </select>
                <select class="select" name="status">
                    <option value="">Все статусы</option>
                    <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Завершена</option>
                    <option value="canceled" <?= $filters['status'] === 'canceled' ? 'selected' : '' ?>>Отменена</option>
                </select>
                <input type="hidden" name="period" value="<?= e($period['period']) ?>">
                <?php if ($period['period'] === 'custom'): ?>
                    <input type="hidden" name="from" value="<?= e($period['from']) ?>">
                    <input type="hidden" name="to" value="<?= e($period['to']) ?>">
                <?php endif; ?>
                <button class="btn btn--ghost btn--sm" type="submit"><?= icon('filter') ?> Показать</button>
            </div>
            <div class="muted" style="font-size: 12px;">
                <?= e(period_label($period['from'], $period['to'])) ?>
                <?php if (!\PIZ\Auth::can('sales.view_all')): ?>
                    · только ваши продажи
                <?php endif; ?>
            </div>
        </form>

        <?php if ($sales === []): ?>
            <div class="empty">
                <?= icon('receipt') ?>
                <p>Продаж за выбранный период нет</p>
                <?php if (\PIZ\Auth::can('pos.use')): ?>
                    <a class="btn btn--soft btn--sm" href="<?= e(url('/pos')) ?>">Открыть кассу</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Чек</th>
                        <th>Дата и время</th>
                        <th>Кассир</th>
                        <th class="num">Позиций</th>
                        <th>Оплата</th>
                        <th class="num">Сумма</th>
                        <?php if ($finance): ?><th class="num">Прибыль</th><?php endif; ?>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><a class="number" href="<?= e(url('/sales/' . $sale['id'])) ?>"><?= e($sale['number']) ?></a></td>
                            <td class="muted"><?= e(dt_human($sale['created_at'])) ?></td>
                            <td><?= e($sale['cashier_name'] ?? '—') ?></td>
                            <td class="num"><?= (int) $sale['items_count'] ?></td>
                            <td><?= payment_badge((string) $sale['payment_method']) ?></td>
                            <td class="num strong"><?= e(money((int) $sale['total_kop'])) ?></td>
                            <?php if ($finance): ?>
                                <td class="num" style="color: var(--green); font-weight: 650;"><?= e(money((int) $sale['total_kop'] - (int) $sale['cost_kop'])) ?></td>
                            <?php endif; ?>
                            <td><?= sale_status_badge((string) $sale['status']) ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="icon-btn" href="<?= e(url('/sales/' . $sale['id'])) ?>" title="Открыть чек"><?= icon('eye') ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <?php $startPage = max(1, $page - 2); $endPage = min($pages, $page + 2); ?>
                <div class="pagination">
                    <span class="pagination__info">
                        Страница <?= $page ?> из <?= $pages ?> · всего <?= (int) $total ?> <?= plural((int) $total, 'чек', 'чека', 'чеков') ?>
                    </span>
                    <div class="pagination__pages">
                        <?php if ($page > 1): ?>
                            <a class="page-btn" href="<?= e(query_url('/sales', ['page' => $page - 1])) ?>"><?= icon('chevron-left', 'icon icon--xs') ?></a>
                        <?php endif; ?>
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a class="page-btn <?= $i === $page ? 'is-active' : '' ?>" href="<?= e(query_url('/sales', ['page' => $i])) ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $pages): ?>
                            <a class="page-btn" href="<?= e(query_url('/sales', ['page' => $page + 1])) ?>"><?= icon('chevron-right', 'icon icon--xs') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
