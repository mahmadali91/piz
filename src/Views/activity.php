<?php
$periodChips = [
    'today' => 'Сегодня',
    '7d' => '7 дней',
    '30d' => '30 дней',
    'month' => 'Этот месяц',
    'all' => 'Всё время',
];
?>
<div class="page">
    <div class="card">
        <div class="toolbar" style="margin-bottom: 14px;">
            <div class="chips">
                <?php foreach ($periodChips as $key => $label): ?>
                    <a class="chip <?= $period['period'] === $key ? 'is-active' : '' ?>"
                       href="<?= e(query_url('/activity', ['period' => $key, 'from' => null, 'to' => null, 'page' => null])) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="get" action="<?= e(url('/activity')) ?>" class="toolbar" style="margin-bottom: 12px;">
            <div class="toolbar__left">
                <div class="search-box">
                    <?= icon('search') ?>
                    <input class="input" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Детали, имя или IP">
                </div>
                <select class="select" name="action">
                    <option value="">Все действия</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= e($action['action']) ?>" <?= $filters['action'] === $action['action'] ? 'selected' : '' ?>>
                            <?= e(audit_label((string) $action['action'])) ?> (<?= (int) $action['cnt'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="select" name="user_id">
                    <option value="">Все сотрудники</option>
                    <?php foreach ($users as $item): ?>
                        <option value="<?= (int) $item['id'] ?>" <?= (int) $filters['user_id'] === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="period" value="<?= e($period['period']) ?>">
                <?php if ($period['period'] === 'custom'): ?>
                    <input type="hidden" name="from" value="<?= e($period['from']) ?>">
                    <input type="hidden" name="to" value="<?= e($period['to']) ?>">
                <?php endif; ?>
                <button class="btn btn--ghost btn--sm" type="submit"><?= icon('filter') ?> Показать</button>
            </div>
            <div class="muted" style="font-size: 12px;"><?= e(period_label($period['from'], $period['to'])) ?> · <?= (int) $total ?> <?= plural((int) $total, 'запись', 'записи', 'записей') ?></div>
        </form>

        <?php if ($rows === []): ?>
            <div class="empty"><?= icon('activity') ?><p>Записей нет</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Когда</th>
                        <th>Сотрудник</th>
                        <th>Действие</th>
                        <th>Детали</th>
                        <th>IP</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="muted" style="white-space: nowrap;"><?= e(dt_human($row['created_at'])) ?></td>
                            <td>
                                <div class="audit-user">
                                    <?php if ($row['user_name']): ?>
                                        <span class="avatar avatar--sm" style="width: 26px; height: 26px; font-size: 9px;"><?= e(initials((string) $row['user_name'])) ?></span>
                                        <span><?= e($row['user_name']) ?></span>
                                    <?php else: ?>
                                        <span class="muted">система</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="badge badge--gray"><?= e(audit_label((string) $row['action'])) ?></span></td>
                            <td><div class="audit-details" title="<?= e((string) $row['details']) ?>"><?= e((string) $row['details']) ?></div></td>
                            <td class="muted" style="white-space: nowrap;"><?= e($row['ip'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <?php $startPage = max(1, $page - 2); $endPage = min($pages, $page + 2); ?>
                <div class="pagination">
                    <span class="pagination__info">Страница <?= $page ?> из <?= $pages ?></span>
                    <div class="pagination__pages">
                        <?php if ($page > 1): ?>
                            <a class="page-btn" href="<?= e(query_url('/activity', ['page' => $page - 1])) ?>"><?= icon('chevron-left', 'icon icon--xs') ?></a>
                        <?php endif; ?>
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a class="page-btn <?= $i === $page ? 'is-active' : '' ?>" href="<?= e(query_url('/activity', ['page' => $i])) ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $pages): ?>
                            <a class="page-btn" href="<?= e(query_url('/activity', ['page' => $page + 1])) ?>"><?= icon('chevron-right', 'icon icon--xs') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
