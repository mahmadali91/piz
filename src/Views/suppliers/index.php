<div class="page">
    <div class="toolbar">
        <div>
            <h2 style="font-size: 22px;">Поставщики</h2>
            <p class="muted" style="font-size: 12.5px; margin-top: 3px;">Накладные, цены и долг поставщикам</p>
        </div>
        <div class="toolbar__right">
            <button class="btn btn--primary btn--sm" type="button" data-supplier-new><?= icon('plus') ?> Добавить поставщика</button>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/suppliers/invoices/create')) ?>"><?= icon('receipt') ?> Новая накладная</a>
        </div>
    </div>

    <section class="summary-grid">
        <div class="stat"><span class="stat__label">Поставщиков</span><span class="stat__value"><?= count($suppliers) ?></span></div>
        <div class="stat"><span class="stat__label">Долг поставщикам</span><span class="stat__value stat__value--red"><?= e(money((int) $debt)) ?></span></div>
        <div class="stat"><span class="stat__label">Накладных в списке</span><span class="stat__value"><?= count($invoices) ?></span></div>
    </section>

    <section class="card">
        <header class="card__head"><div><h3>Список поставщиков</h3><p>Контакты и текущая задолженность</p></div></header>
        <?php if ($suppliers === []): ?>
            <div class="empty"><?= icon('truck') ?><p>Поставщиков пока нет</p><button class="btn btn--soft btn--sm" type="button" data-supplier-new>Добавить первого</button></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Поставщик</th><th>Контакт</th><th>Телефон</th><th class="num">Накладных</th><th class="num">Долг</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><a class="number" href="<?= e(url('/suppliers/' . $supplier['id'])) ?>"><?= e($supplier['name']) ?></a><?php if (!(int) $supplier['active']): ?> <span class="badge badge--gray">архив</span><?php endif; ?></td>
                            <td class="muted"><?= e($supplier['contact'] ?: '—') ?></td>
                            <td class="muted"><?= e($supplier['phone'] ?: '—') ?></td>
                            <td class="num"><?= (int) $supplier['invoice_count'] ?></td>
                            <td class="num <?= (int) $supplier['debt_kop'] > 0 ? 'stat__value--red' : '' ?>"><?= e(money((int) $supplier['debt_kop'])) ?></td>
                            <td><div class="row-actions"><a class="icon-btn" href="<?= e(url('/suppliers/' . $supplier['id'])) ?>" title="Открыть"><?= icon('eye') ?></a><button class="icon-btn" type="button" title="Редактировать" data-supplier-edit="<?= e(json_encode($supplier, JSON_UNESCAPED_UNICODE)) ?>"><?= icon('edit') ?></button></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <header class="card__head"><div><h3>Последние накладные</h3><p>Цены, оплаты и долг</p></div><a class="link" href="<?= e(url('/suppliers/invoices')) ?>">Все накладные <?= icon('arrow-right', 'icon icon--xs') ?></a></header>
        <?php if ($invoices === []): ?>
            <div class="empty empty--compact"><?= icon('receipt') ?><p>Накладных пока нет</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead><tr><th>Накладная</th><th>Поставщик</th><th>Дата</th><th class="num">Сумма</th><th class="num">Долг</th><th>Статус</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><a class="number" href="<?= e(url('/suppliers/invoices/' . $invoice['id'])) ?>"><?= e($invoice['number']) ?></a></td>
                            <td><?= e($invoice['supplier_name'] ?? '—') ?></td>
                            <td class="muted"><?= e(date('d.m.Y', strtotime((string) $invoice['invoice_date']))) ?></td>
                            <td class="num strong"><?= e(money((int) $invoice['total_kop'])) ?></td>
                            <td class="num"><?= e(money((int) $invoice['debt_kop'])) ?></td>
                            <td><?= invoice_status_badge((string) $invoice['status']) ?></td>
                            <td><a class="icon-btn" href="<?= e(url('/suppliers/invoices/' . $invoice['id'] . '/pdf')) ?>" title="Скачать PDF"><?= icon('download') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="modal" id="supplier-modal" aria-hidden="true">
    <div class="modal__backdrop" data-modal-close></div>
    <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="supplier-title">
        <div class="modal__head"><div><h3 id="supplier-title">Поставщик</h3><p>Контакты и реквизиты</p></div><button class="icon-btn" type="button" data-modal-close><?= icon('close') ?></button></div>
        <form method="post" action="<?= e(url('/suppliers/save')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="modal__body"><div class="stack">
                <label class="field"><span class="label">Название *</span><input class="input" type="text" name="name" maxlength="160" required></label>
                <div class="form-grid">
                    <label class="field"><span class="label">Контактное лицо</span><input class="input" type="text" name="contact" maxlength="120"></label>
                    <label class="field"><span class="label">Телефон</span><input class="input" type="text" name="phone" maxlength="40"></label>
                    <label class="field"><span class="label">Email</span><input class="input" type="email" name="email" maxlength="120"></label>
                    <label class="field"><span class="label">ИНН</span><input class="input" type="text" name="inn" maxlength="20"></label>
                </div>
                <label class="field"><span class="label">Адрес</span><input class="input" type="text" name="address" maxlength="255"></label>
                <label class="field"><span class="label">Заметка</span><textarea class="input" name="note" rows="2" maxlength="500"></textarea></label>
                <label class="checkbox"><input type="checkbox" name="active" value="1" checked> Активен</label>
            </div></div>
            <div class="modal__foot"><button class="btn btn--ghost" type="button" data-modal-close>Отмена</button><button class="btn btn--primary" type="submit">Сохранить</button></div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('supplier-modal');
    if (!modal) return;
    var form = modal.querySelector('form');
    function fill(data) {
        form.reset();
        form.elements.id.value = data.id || '';
        ['name','contact','phone','email','address','inn','note'].forEach(function (key) {
            if (form.elements[key]) form.elements[key].value = data[key] || '';
        });
        form.elements.active.checked = data.id ? Number(data.active) === 1 : true;
    }
    document.querySelectorAll('[data-supplier-new]').forEach(function (b) { b.addEventListener('click', function () { fill({}); window.PIZ_openModal('supplier-modal'); }); });
    document.querySelectorAll('[data-supplier-edit]').forEach(function (b) { b.addEventListener('click', function () { try { fill(JSON.parse(b.getAttribute('data-supplier-edit'))); } catch (e) { fill({}); } window.PIZ_openModal('supplier-modal'); }); });
});
</script>
