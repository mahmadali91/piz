<div class="page">
    <div class="toolbar">
        <div class="toolbar__left"><a class="btn btn--ghost btn--sm" href="<?= e(url('/suppliers/invoices')) ?>"><?= icon('chevron-left', 'icon icon--xs') ?> Накладные</a><div><h2 style="font-size: 20px;">Новая накладная</h2><div class="muted" style="font-size: 12px;">Товар оприходуется и попадёт в историю цен</div></div></div>
    </div>
    <?php if ($suppliers === []): ?>
        <div class="alert alert--warn"><?= icon('alert') ?><span>Сначала добавьте поставщика в разделе «Поставщики».</span></div>
    <?php else: ?>
    <form method="post" action="<?= e(url('/suppliers/invoices')) ?>" id="invoice-form">
        <?= csrf_field() ?>
        <section class="card">
            <div class="form-grid form-grid--3">
                <label class="field"><span class="label">Поставщик *</span><select class="select" name="supplier_id" required><option value="">Выберите</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span class="label">Номер накладной</span><input class="input" type="text" name="number" maxlength="64" placeholder="Если пусто — автоматически"></label>
                <label class="field"><span class="label">Дата</span><input class="input" type="date" name="invoice_date" value="<?= e(date('Y-m-d')) ?>" required></label>
                <label class="field"><span class="label">Срок оплаты</span><input class="input" type="date" name="due_date"></label>
                <label class="field span-2"><span class="label">Комментарий</span><input class="input" type="text" name="comment" maxlength="255" placeholder="Накладная, доставка…"></label>
            </div>
        </section>
        <section class="card">
            <header class="card__head"><div><h3>Позиции</h3><p>Количество и закупочная цена за единицу</p></div><button class="btn btn--soft btn--sm" type="button" data-invoice-add><?= icon('plus') ?> Добавить строку</button></header>
            <div class="table-wrap"><table class="table" id="invoice-items">
                <thead><tr><th>Товар</th><th class="num">Кол-во</th><th class="num">Цена, ₽</th><th class="num">Сумма</th><th></th></tr></thead>
                <tbody>
                <tr class="invoice-row">
                    <td><select class="select" name="product_id[]" required><option value="">Товар</option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>" data-cost="<?= e(kop_to_input((int) $p['purchase_price_kop'])) ?>"><?= e($p['name']) ?> · остаток <?= (int) $p['stock_qty'] ?></option><?php endforeach; ?></select></td>
                    <td class="num"><input class="input input--qty" type="number" name="qty[]" min="1" value="1" required></td>
                    <td class="num"><input class="input input--money" type="text" name="unit_cost[]" inputmode="decimal" required placeholder="0"></td>
                    <td class="num row-total">0 ₽</td>
                    <td><button class="icon-btn" type="button" data-invoice-remove title="Удалить"><?= icon('trash') ?></button></td>
                </tr>
                </tbody>
                <tfoot><tr><th colspan="3" class="num">Итого</th><th class="num" id="invoice-total">0 ₽</th><th></th></tr></tfoot>
            </table></div>
            <div class="form-actions" style="margin-top: 14px;"><button class="btn btn--primary" type="submit"><?= icon('check') ?> Провести накладную</button></div>
        </section>
    </form>
    <?php endif; ?>
</div>
<template id="invoice-row-template">
    <tr class="invoice-row">
        <td><select class="select" name="product_id[]" required><option value="">Товар</option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>" data-cost="<?= e(kop_to_input((int) $p['purchase_price_kop'])) ?>"><?= e($p['name']) ?> · остаток <?= (int) $p['stock_qty'] ?></option><?php endforeach; ?></select></td>
        <td class="num"><input class="input input--qty" type="number" name="qty[]" min="1" value="1" required></td>
        <td class="num"><input class="input input--money" type="text" name="unit_cost[]" inputmode="decimal" required placeholder="0"></td>
        <td class="num row-total">0 ₽</td>
        <td><button class="icon-btn" type="button" data-invoice-remove title="Удалить"><?= icon('trash') ?></button></td>
    </tr>
</template>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.querySelector('#invoice-items tbody');
    var totalEl = document.getElementById('invoice-total');
    if (!tbody) return;
    function parse(v) { return parseFloat(String(v).replace(',', '.')) || 0; }
    function syncRow(row) {
        var qty = parseInt(row.querySelector('[name="qty[]"]').value, 10) || 0;
        var price = parse(row.querySelector('[name="unit_cost[]"]').value);
        row.querySelector('.row-total').textContent = (qty * price).toFixed(0) + ' ₽';
    }
    function syncTotal() {
        var sum = 0;
        tbody.querySelectorAll('.invoice-row').forEach(function (row) {
            var qty = parseInt(row.querySelector('[name="qty[]"]').value, 10) || 0;
            var price = parse(row.querySelector('[name="unit_cost[]"]').value);
            sum += qty * price;
        });
        totalEl.textContent = sum.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
    }
    tbody.addEventListener('input', function (e) {
        var row = e.target.closest('.invoice-row');
        if (!row) return;
        if (e.target.matches('select')) {
            var cost = e.target.selectedOptions[0] && e.target.selectedOptions[0].dataset.cost;
            var input = row.querySelector('[name="unit_cost[]"]');
            if (cost && !input.value) input.value = cost;
        }
        syncRow(row); syncTotal();
    });
    tbody.addEventListener('click', function (e) {
        var rm = e.target.closest('[data-invoice-remove]');
        if (rm) { var rows = tbody.querySelectorAll('.invoice-row'); if (rows.length > 1) { rm.closest('tr').remove(); syncTotal(); } }
    });
    document.querySelector('[data-invoice-add]').addEventListener('click', function () {
        var tpl = document.getElementById('invoice-row-template');
        tbody.appendChild(tpl.content.cloneNode(true));
    });
    tbody.querySelectorAll('.invoice-row').forEach(syncRow);
});
</script>
