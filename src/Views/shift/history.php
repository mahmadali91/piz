<div class="page">
    <div class="toolbar">
        <div>
            <h2 style="font-size: 22px;">История смен</h2>
            <p class="muted" style="font-size: 12.5px; margin-top: 3px;">Отчёты по остаткам и выручке</p>
        </div>
        <div class="toolbar__right">
            <a class="btn btn--primary btn--sm" href="<?= e(url('/shift')) ?>"><?= icon('clock') ?> Текущая смена</a>
        </div>
    </div>

    <section class="card">
        <?php if ($shifts === []): ?>
            <div class="empty"><?= icon('clock') ?><p>Смен пока нет</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Смена</th><th>Открыта</th><th>Закрыта</th><th>Открыл</th>
                        <th class="num">Продаж</th><th class="num">Выручка</th>
                        <?php if ($finance): ?><th class="num">Прибыль</th><?php endif; ?>
                        <th class="num">Остаток</th><th>Статус</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($shifts as $shift): ?>
                        <tr>
                            <td><a class="number" href="<?= e(url('/shifts/' . $shift['id'])) ?>">№ <?= (int) $shift['id'] ?></a></td>
                            <td class="muted"><?= e(dt_human($shift['opened_at'])) ?></td>
                            <td class="muted"><?= e($shift['closed_at'] ? dt_human($shift['closed_at']) : '—') ?></td>
                            <td><?= e($shift['opened_by_name'] ?? '—') ?></td>
                            <td class="num"><?= (int) $shift['sales_count'] ?></td>
                            <td class="num strong"><?= e(money((int) $shift['revenue_kop'])) ?></td>
                            <?php if ($finance): ?>
                                <td class="num" style="color: var(--green); font-weight: 650;"><?= e(money((int) $shift['profit_kop'])) ?></td>
                            <?php endif; ?>
                            <td class="num"><?= (int) $shift['stock_units'] ?></td>
                            <td><?= $shift['status'] === 'open' ? '<span class="badge badge--green">Открыта</span>' : '<span class="badge badge--gray">Закрыта</span>' ?></td>
                            <td><a class="icon-btn" href="<?= e(url('/shifts/' . $shift['id'])) ?>"><?= icon('eye') ?></a></td>
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
                        <?php if ($page > 1): ?><a class="page-btn" href="<?= e(query_url('/shifts', ['page' => $page - 1])) ?>"><?= icon('chevron-left', 'icon icon--xs') ?></a><?php endif; ?>
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a class="page-btn <?= $i === $page ? 'is-active' : '' ?>" href="<?= e(query_url('/shifts', ['page' => $i])) ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $pages): ?><a class="page-btn" href="<?= e(query_url('/shifts', ['page' => $page + 1])) ?>"><?= icon('chevron-right', 'icon icon--xs') ?></a><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
