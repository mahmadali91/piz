<?php
/** @var array $receiptTotals */
?>
<div class="page">

    <section class="summary-grid">
        <div class="stat"><span class="stat__label">Приходов в истории</span><span class="stat__value"><?= count($receipts) ?></span></div>
        <div class="stat"><span class="stat__label">Принято единиц</span><span class="stat__value"><?= (int) $receiptTotals['qty'] ?></span></div>
        <?php if ($finance): ?>
            <div class="stat"><span class="stat__label">Сумма приходов</span><span class="stat__value"><?= e(money((int) $receiptTotals['total'])) ?></span></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <header class="card__head">
            <div><h3>Новый приход</h3><p>Каждая партия сохраняет свою себестоимость</p></div>
            <?= icon('truck', 'icon icon--muted') ?>
        </header>
        <form method="post" action="<?= e(url('/stock/in')) ?>" id="stock-in-form">
            <?= csrf_field() ?>
            <div class="table-wrap">
                <table class="table" id="stock-in-rows">
                    <thead>
                    <tr>
                        <th>Товар</th>
                        <th class="num">Кол-во</th>
                        <?php if ($canPrices): ?><th class="num">Себестоимость, ₽</th><?php endif; ?>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="stock-row">
                        <td>
                            <select class="select" name="product_id[]" required>
                                <option value="">Выберите товар</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= (int) $product['id'] ?>" data-cost="<?= e(kop_to_input((int) $product['purchase_price_kop'])) ?>">
                                        <?= e($product['name']) ?> · сейчас <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?>
                                        <?php if ($finance && $canPrices): ?> · себестоимость <?= e(kop_to_input((int) $product['purchase_price_kop'])) ?> ₽<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="num"><input class="input input--qty" type="number" name="qty[]" min="1" max="100000" value="1" required></td>
                        <?php if ($canPrices): ?><td class="num"><input class="input input--money" type="text" name="unit_cost[]" inputmode="decimal" placeholder="Средняя"></td><?php endif; ?>
                        <td><button class="icon-btn" type="button" data-stock-remove title="Удалить"><?= icon('trash') ?></button></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="form-grid form-grid--3" style="margin-top: 14px;">
                <label class="field span-2">
                    <span class="label">Комментарий</span>
                    <input class="input" type="text" name="comment" maxlength="255" placeholder="Поставщик, накладная…">
                </label>
                <div class="field" style="justify-content: flex-end;">
                    <span class="label">Итого</span>
                    <div class="stock-total" id="stock-in-summary">0 позиций · 0 ед.</div>
                </div>
            </div>
            <?php if (!$canPrices): ?>
                <div class="alert alert--info" style="margin-top: 12px;"><?= icon('alert') ?><span>Себестоимость берётся из карточки товара. Менять цены может администратор с правом «Изменение цен».</span></div>
            <?php endif; ?>
            <div class="form-actions" style="margin-top: 8px; flex-wrap: wrap;">
                <button class="btn btn--ghost" type="button" data-stock-add><?= icon('plus') ?> Добавить товар</button>
                <button class="btn btn--primary" type="submit"><?= icon('check') ?> Оприходовать всё</button>
            </div>
        </form>
    </section>

    <section class="card">
        <header class="card__head">
            <div><h3>История приходов</h3><p>Партии и их себестоимость</p></div>
            <a class="link" href="<?= e(url('/stock')) ?>">Остаток <?= icon('arrow-right', 'icon icon--xs') ?></a>
        </header>
        <?php if ($receipts === []): ?>
            <div class="empty empty--compact"><?= icon('truck') ?><p>Приходов пока нет</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead>
                    <tr>
                        <th>Когда</th><th>Товар</th><th class="num">Кол-во</th>
                        <?php if ($finance): ?><th class="num">Себестоимость</th><th class="num">Сумма</th><?php endif; ?>
                        <th>Сотрудник</th><th>Комментарий</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td class="muted" style="white-space: nowrap;"><?= e(dt_human($receipt['created_at'])) ?></td>
                            <td class="strong"><?= e($receipt['product_name'] ?? '—') ?></td>
                            <td class="num"><?= (int) $receipt['qty'] ?> <?= e($receipt['unit'] ?? '') ?></td>
                            <?php if ($finance): ?>
                                <td class="num"><?= e(money((int) $receipt['unit_cost_kop'])) ?></td>
                                <td class="num strong"><?= e(money((int) $receipt['total_kop'])) ?></td>
                            <?php endif; ?>
                            <td><?= e($receipt['user_name'] ?? '—') ?></td>
                            <td class="muted"><?= e($receipt['comment'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<template id="stock-in-template">
    <tr class="stock-row">
        <td>
            <select class="select" name="product_id[]" required>
                <option value="">Выберите товар</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int) $product['id'] ?>" data-cost="<?= e(kop_to_input((int) $product['purchase_price_kop'])) ?>">
                        <?= e($product['name']) ?> · сейчас <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="num"><input class="input input--qty" type="number" name="qty[]" min="1" max="100000" value="1" required></td>
        <?php if ($canPrices): ?><td class="num"><input class="input input--money" type="text" name="unit_cost[]" inputmode="decimal" placeholder="Средняя"></td><?php endif; ?>
        <td><button class="icon-btn" type="button" data-stock-remove title="Удалить"><?= icon('trash') ?></button></td>
    </tr>
</template>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('stock-in-form');
    if (!form) return;
    var tbody = form.querySelector('#stock-in-rows tbody');
    var summary = document.getElementById('stock-in-summary');
    function sync() {
        var rows = tbody.querySelectorAll('.stock-row');
        var qty = 0;
        rows.forEach(function (row) {
            qty += parseInt(row.querySelector('[name="qty[]"]').value, 10) || 0;
        });
        summary.textContent = rows.length + ' ' + (rows.length === 1 ? 'позиция' : (rows.length < 5 ? 'позиции' : 'позиций')) + ' · ' + qty + ' ед.';
    }
    tbody.addEventListener('input', function (e) {
        if (e.target.matches('select')) {
            var row = e.target.closest('.stock-row');
            var cost = e.target.selectedOptions[0] && e.target.selectedOptions[0].dataset.cost;
            var input = row.querySelector('[name="unit_cost[]"]');
            if (input && cost) input.value = cost;
        }
        sync();
    });
    tbody.addEventListener('click', function (e) {
        var rm = e.target.closest('[data-stock-remove]');
        if (!rm) return;
        if (tbody.querySelectorAll('.stock-row').length > 1) {
            rm.closest('tr').remove();
            sync();
        }
    });
    document.querySelector('[data-stock-add]').addEventListener('click', function () {
        tbody.appendChild(document.getElementById('stock-in-template').content.cloneNode(true));
        sync();
    });
    sync();
});
</script>

</div>
