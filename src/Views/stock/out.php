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
        <form method="post" action="<?= e(url('/stock/out')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid form-grid--3">
                <label class="field span-2">
                    <span class="label">Товар</span>
                    <select class="select" name="product_id" required>
                        <option value="">Выберите товар</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int) $product['id'] ?>">
                                <?= e($product['name']) ?> · остаток <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?>
                                <?php if ($finance): ?> · себестоимость <?= e(kop_to_input((int) $product['purchase_price_kop'])) ?> ₽<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span class="label">Количество</span>
                    <input class="input" type="number" name="qty" min="1" max="100000" required placeholder="Например, 1">
                </label>
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
                <label class="field span-2">
                    <span class="label">Комментарий</span>
                    <input class="input" type="text" name="comment" maxlength="255" placeholder="Например, обед смены">
                </label>
            </div>
            <?php if ($allowNegative): ?>
                <div class="alert alert--warn" style="margin-top: 12px;"><?= icon('alert') ?><span>Если товара не хватает, остаток может уйти в минус.</span></div>
            <?php endif; ?>
            <div class="form-actions" style="margin-top: 8px;">
                <button class="btn btn--danger" type="submit"><?= icon('logout') ?> Списать как расход</button>
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
</div>
