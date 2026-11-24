<?php
$expenseTotal = array_sum(array_map(static fn ($row) => (int) $row['total_kop'], $writeoffs));
$expenseQty = array_sum(array_map(static fn ($row) => (int) $row['qty'], $writeoffs));
?>
<div class="page">

    <section class="summary-grid">
        <div class="stat"><span class="stat__label">Списаний в истории</span><span class="stat__value"><?= count($writeoffs) ?></span></div>
        <div class="stat"><span class="stat__label">Списано единиц</span><span class="stat__value"><?= (int) $expenseQty ?></span></div>
        <?php if ($finance): ?>
            <div class="stat"><span class="stat__label">Сумма расходов</span><span class="stat__value stat__value--red"><?= e(money((int) $expenseTotal)) ?></span></div>
        <?php endif; ?>
    </section>

    <div class="alert alert--info">
        <?= icon('alert') ?>
        <span>Это <strong>не продажа</strong>: списание уменьшает остаток и учитывается как расход. Выручка и средний чек не увеличиваются.</span>
    </div>

    <section class="card">
        <header class="card__head">
            <div><h3>Списать товар</h3><p>Питание сотрудника, личное использование, брак</p></div>
            <?= icon('logout', 'icon icon--muted') ?>
        </header>
        <form method="post" action="<?= e(url('/stock/out')) ?>" id="stock-out-form">
            <?= csrf_field() ?>
            <div class="table-wrap">
                <table class="table" id="stock-out-rows">
                    <thead><tr><th>Товар</th><th class="num">Кол-во</th><th></th></tr></thead>
                    <tbody>
                    <tr class="stock-row">
                        <td>
                            <select class="select" name="product_id[]" required>
                                <option value="">Выберите товар</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= (int) $product['id'] ?>">
                                        <?= e($product['name']) ?> · остаток <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?>
                                        <?php if ($finance): ?> · себестоимость <?= e(kop_to_input((int) $product['purchase_price_kop'])) ?> ₽<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="num"><input class="input input--qty" type="number" name="qty[]" min="1" max="100000" value="1" required></td>
                        <td><button class="icon-btn" type="button" data-stock-out-remove title="Удалить"><?= icon('trash') ?></button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="form-grid form-grid--3" style="margin-top: 14px;">
                <label class="field">
                    <span class="label">Сотрудник</span>
                    <input class="input" type="text" name="employee_name" list="employees-list" maxlength="120" placeholder="Кто использовал">
                    <datalist id="employees-list">
                        <?php foreach ($employees as $employee): ?><option value="<?= e($employee['name']) ?>"></option><?php endforeach; ?>
                    </datalist>
                </label>
                <label class="field">
                    <span class="label">Причина</span>
                    <select class="select" name="reason">
                        <?php foreach ($reasons as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span class="label">Комментарий</span>
                    <input class="input" type="text" name="comment" maxlength="255" placeholder="Например, обед смены">
                </label>
            </div>
            <div class="stock-total" id="stock-out-summary" style="margin-top: 10px;">0 позиций · 0 ед.</div>
            <?php if ($allowNegative): ?>
                <div class="alert alert--warn" style="margin-top: 12px;"><?= icon('alert') ?><span>Если товара не хватает, остаток может уйти в минус.</span></div>
            <?php endif; ?>
            <div class="form-actions" style="margin-top: 8px; flex-wrap: wrap;">
                <button class="btn btn--ghost" type="button" data-stock-out-add><?= icon('plus') ?> Добавить товар</button>
                <button class="btn btn--danger" type="submit"><?= icon('logout') ?> Списать всё как расход</button>
            </div>
        </form>
    </section>

    <section class="card">
        <header class="card__head">
            <div><h3>История расходов</h3><p>Кто и что списал</p></div>
            <?= icon('list', 'icon icon--muted') ?>
        </header>
        <?php if ($writeoffs === []): ?>
            <div class="empty empty--compact"><?= icon('check') ?><p>Списаний пока нет</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead>
                    <tr>
                        <th>Когда</th><th>Товар</th><th class="num">Кол-во</th><th>Сотрудник</th><th>Причина</th>
                        <?php if ($finance): ?><th class="num">Расход</th><?php endif; ?>
                        <th>Оформил</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($writeoffs as $row): ?>
                        <tr>
                            <td class="muted" style="white-space: nowrap;"><?= e(dt_human($row['created_at'])) ?></td>
                            <td class="strong"><?= e($row['product_name'] ?? '—') ?></td>
                            <td class="num"><?= (int) $row['qty'] ?> <?= e($row['unit'] ?? '') ?></td>
                            <td><?= e($row['employee_name'] ?: '—') ?></td>
                            <td><span class="badge badge--red"><?= e($row['reason']) ?></span></td>
                            <?php if ($finance): ?><td class="num" style="color: var(--red);"><?= e(money((int) $row['total_kop'])) ?></td><?php endif; ?>
                            <td class="muted"><?= e($row['user_name'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<template id="stock-out-template">
    <tr class="stock-row">
        <td>
            <select class="select" name="product_id[]" required>
                <option value="">Выберите товар</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?> · остаток <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="num"><input class="input input--qty" type="number" name="qty[]" min="1" max="100000" value="1" required></td>
        <td><button class="icon-btn" type="button" data-stock-out-remove title="Удалить"><?= icon('trash') ?></button></td>
    </tr>
</template>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('stock-out-form');
    if (!form) return;
    var tbody = form.querySelector('#stock-out-rows tbody');
    var summary = document.getElementById('stock-out-summary');
    function sync() {
        var rows = tbody.querySelectorAll('.stock-row');
        var qty = 0;
        rows.forEach(function (row) { qty += parseInt(row.querySelector('[name="qty[]"]').value, 10) || 0; });
        summary.textContent = rows.length + ' ' + (rows.length === 1 ? 'позиция' : (rows.length < 5 ? 'позиции' : 'позиций')) + ' · ' + qty + ' ед.';
    }
    tbody.addEventListener('input', sync);
    tbody.addEventListener('click', function (e) {
        var rm = e.target.closest('[data-stock-out-remove]');
        if (!rm) return;
        if (tbody.querySelectorAll('.stock-row').length > 1) {
            rm.closest('tr').remove();
            sync();
        }
    });
    document.querySelector('[data-stock-out-add]').addEventListener('click', function () {
        tbody.appendChild(document.getElementById('stock-out-template').content.cloneNode(true));
        sync();
    });
    sync();
});
</script>

</div>
