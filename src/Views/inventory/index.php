<div class="page">
    <div class="toolbar">
        <div><h2 style="font-size: 22px;">Инвентаризация</h2><p class="muted" style="font-size: 12.5px; margin-top: 3px;">Документы пересчёта склада и акты расхождений</p></div>
    </div>

    <section class="card">
        <header class="card__head"><div><h3>Новая инвентаризация</h3><p>Начните пересчёт — остатки зафиксируются на момент создания</p></div></header>
        <form method="post" action="<?= e(url('/inventory/create')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid form-grid--3">
                <label class="field"><span class="label">Склад</span><input class="input" type="text" name="warehouse" value="Основной склад" maxlength="120" required></label>
                <label class="field span-2"><span class="label">Комментарий</span><input class="input" type="text" name="note" maxlength="255" placeholder="Плановая, перед сменой, после поставки…"></label>
            </div>
            <div class="form-actions"><button class="btn btn--primary" type="submit"><?= icon('plus') ?> Начать инвентаризацию</button></div>
        </form>
    </section>

    <section class="card">
        <header class="card__head"><div><h3>История инвентаризаций</h3><p>Кто, когда и какой склад</p></div></header>
        <?php if ($sessions === []): ?><div class="empty"><?= icon('list') ?><p>Инвентаризаций пока нет</p></div><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Номер</th><th>Склад</th><th>Начата</th><th>Кто начал</th><th>Завершена</th><th class="num">Позиций</th><th class="num">Расхождение</th><th>Статус</th><th></th></tr></thead>
            <tbody><?php foreach ($sessions as $s): ?>
                <tr>
                    <td><a class="number" href="<?= e(url('/inventory/' . $s['id'])) ?>"><?= e($s['number']) ?></a></td>
                    <td><?= e($s['warehouse']) ?></td>
                    <td class="muted"><?= e(dt_human($s['started_at'])) ?></td>
                    <td><?= e($s['started_by_name'] ?? '—') ?></td>
                    <td class="muted"><?= e($s['completed_at'] ? dt_human($s['completed_at']) : '—') ?></td>
                    <td class="num"><?= (int) $s['total_products'] ?></td>
                    <td class="num <?= (int) $s['total_diff'] !== 0 ? 'stat__value--red' : '' ?>"><?= (int) $s['total_diff'] > 0 ? '+' : '' ?><?= (int) $s['total_diff'] ?></td>
                    <td><?= $s['status'] === 'completed' ? '<span class="badge badge--green">Завершена</span>' : '<span class="badge badge--amber">Черновик</span>' ?></td>
                    <td><div class="row-actions"><a class="icon-btn" href="<?= e(url('/inventory/' . $s['id'])) ?>"><?= icon('eye') ?></a><?php if ($s['status'] === 'completed'): ?><a class="icon-btn" href="<?= e(pdf_url('/inventory/' . $s['id'] . '/pdf')) ?>" title="Скачать акт PDF"><?= icon('download') ?></a><?php endif; ?></div></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </section>
</div>
