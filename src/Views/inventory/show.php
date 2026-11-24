<?php $completed = $session['status'] === 'completed'; ?>
<div class="page">
    <div class="toolbar">
        <div class="toolbar__left"><a class="btn btn--ghost btn--sm" href="<?= e(url('/inventory')) ?>"><?= icon('chevron-left', 'icon icon--xs') ?> Инвентаризация</a><div><h2 style="font-size: 20px;"><?= e($session['number']) ?></h2><div class="muted" style="font-size: 12px;"><?= e($session['warehouse']) ?> · <?= e(dt_human($session['started_at'])) ?> · <?= e($session['started_by_name'] ?? '—') ?></div></div></div>
        <div class="toolbar__right">
            <?php if ($completed): ?><a class="btn btn--primary btn--sm" href="<?= e(url('/inventory/' . $session['id'] . '/pdf')) ?>"><?= icon('download') ?> Скачать акт PDF</a><?php endif; ?>
            <span class="badge <?= $completed ? 'badge--green' : 'badge--amber' ?>"><?= $completed ? 'Завершена' : 'Черновик' ?></span>
        </div>
    </div>

    <section class="summary-grid">
        <div class="stat"><span class="stat__label">Позиций</span><span class="stat__value"><?= (int) $session['total_products'] ?></span></div>
        <div class="stat"><span class="stat__label">Расчётный остаток</span><span class="stat__value"><?= (int) $session['total_calculated'] ?></span></div>
        <div class="stat"><span class="stat__label">Фактический остаток</span><span class="stat__value"><?= (int) $session['total_actual'] ?></span></div>
        <div class="stat"><span class="stat__label">Расхождение</span><span class="stat__value <?= (int) $session['total_diff'] !== 0 ? 'stat__value--red' : '' ?>"><?= (int) $session['total_diff'] > 0 ? '+' : '' ?><?= (int) $session['total_diff'] ?></span></div>
        <div class="stat"><span class="stat__label">Сумма расхождения</span><span class="stat__value <?= (int) $session['total_value_kop'] < 0 ? 'stat__value--red' : '' ?>"><?= e(money((int) $session['total_value_kop'])) ?></span></div>
    </section>

    <form method="post" action="<?= e(url('/inventory/' . $session['id'] . '/save')) ?>" id="inventory-form">
        <?= csrf_field() ?>
        <section class="card">
            <header class="card__head"><div><h3>Ведомость пересчёта</h3><p><?= $completed ? 'Зафиксированные фактические остатки' : 'Внесите фактическое количество; пустое поле = расчётный остаток' ?></p></div><?php if (!$completed): ?><button class="btn btn--ghost btn--sm" type="button" data-fill-actual><?= icon('check') ?> Заполнить фактом</button><?php endif; ?></header>
            <div class="table-wrap"><table class="table table--compact">
                <thead><tr><th>Товар</th><th>Артикул</th><th class="num">Расчёт</th><th class="num">Факт</th><th class="num">Разница</th><th class="num">Сумма</th></tr></thead>
                <tbody><?php foreach ($lines as $line): $diff = (int) $line['diff_qty']; ?>
                    <tr>
                        <td class="strong"><?= e($line['name']) ?></td>
                        <td class="muted"><?= e($line['sku'] ?: '—') ?></td>
                        <td class="num"><?= (int) $line['calculated_qty'] ?> <?= e($line['unit']) ?></td>
                        <td class="num"><?php if ($completed): ?><?= (int) ($line['actual_qty'] ?? $line['calculated_qty']) ?><?php else: ?><input class="input input--inv" type="number" name="actual[<?= (int) $line['product_id'] ?>]" value="<?= e($line['actual_qty'] ?? $line['calculated_qty']) ?>" data-calc="<?= (int) $line['calculated_qty'] ?>" data-cost="<?= (int) $line['cost_kop'] ?>"><?php endif; ?></td>
                        <td class="num diff-cell" style="color: <?= $diff < 0 ? 'var(--red)' : ($diff > 0 ? 'var(--green)' : 'var(--muted)') ?>;"><?= $diff > 0 ? '+' : '' ?><?= $diff ?></td>
                        <td class="num"><?= e(money((int) $line['diff_value_kop'])) ?></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php if (!$completed): ?>
            <div class="form-actions" style="flex-wrap: wrap; margin-top: 14px;">
                <button class="btn btn--ghost" type="submit"><?= icon('check') ?> Сохранить черновик</button>
                <button class="btn btn--primary" type="submit" formaction="<?= e(url('/inventory/' . $session['id'] . '/complete')) ?>" data-confirm="Завершить инвентаризацию и обновить остатки?"><?= icon('check') ?> Завершить и обновить остатки</button>
            </div>
            <?php endif; ?>
        </section>
    </form>
    <?php if ($completed): ?><section class="card"><p class="muted">Завершена <?= e(dt_human($session['completed_at'])) ?> · <?= e($session['completed_by_name'] ?? '—') ?>. Акт недостачи/излишка доступен в PDF.</p></section><?php endif; ?>
</div>
<?php if (!$completed): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('inventory-form');
    if (!form) return;
    function sync(input) {
        var row = input.closest('tr');
        var actual = parseInt(input.value, 10);
        if (isNaN(actual)) actual = parseInt(input.dataset.calc, 10) || 0;
        var diff = actual - (parseInt(input.dataset.calc, 10) || 0);
        var cell = row.querySelector('.diff-cell');
        cell.textContent = (diff > 0 ? '+' : '') + diff;
        cell.style.color = diff < 0 ? 'var(--red)' : (diff > 0 ? 'var(--green)' : 'var(--muted)');
    }
    form.querySelectorAll('input[data-calc]').forEach(function (input) {
        input.addEventListener('input', function () { sync(input); });
    });
    var fill = form.querySelector('[data-fill-actual]');
    if (fill) fill.addEventListener('click', function () {
        form.querySelectorAll('input[data-calc]').forEach(function (input) { input.value = input.dataset.calc; sync(input); });
    });
});
</script>
<?php endif; ?>
