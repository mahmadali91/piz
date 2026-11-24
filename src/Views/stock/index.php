<?php
/** @var array $stats */
?>
<div class="page">

    <section class="summary-grid">
        <div class="stat"><span class="stat__label">Позиций</span><span class="stat__value"><?= (int) $stats['total'] ?></span></div>
        <div class="stat"><span class="stat__label">Единиц на складе</span><span class="stat__value <?= (int) $stats['units'] < 0 ? 'stat__value--red' : '' ?>"><?= (int) $stats['units'] ?></span></div>
        <?php if ($finance): ?>
            <div class="stat"><span class="stat__label">По закупке</span><span class="stat__value"><?= e(money((int) $stats['cost_value'])) ?></span></div>
            <div class="stat"><span class="stat__label">Потенциал продажи</span><span class="stat__value"><?= e(money((int) $stats['retail_value'])) ?></span></div>
        <?php endif; ?>
        <div class="stat <?= $stats['low_count'] > 0 ? 'stat--accent' : '' ?>">
            <span class="stat__label">Ниже минимума</span>
            <span class="stat__value <?= $stats['low_count'] > 0 ? 'stat__value--red' : '' ?>"><?= (int) $stats['low_count'] ?></span>
        </div>
    </section>

    <?php if ($allowNegative): ?>
        <div class="alert alert--info"><?= icon('alert') ?><span>Отрицательные остатки разрешены. Продажа и списание могут уйти в минус.</span></div>
    <?php endif; ?>

    <section class="card">
        <div class="toolbar" style="margin-bottom: 14px;">
            <form method="get" action="<?= e(url('/stock')) ?>" class="toolbar__left">
                <div class="search-box">
                    <?= icon('search') ?>
                    <input class="input" type="search" name="q" value="<?= e($filters['search']) ?>" placeholder="Название, артикул, категория…">
                </div>
                <select class="select" name="category">
                    <option value="">Все категории</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="checkbox"><input type="checkbox" name="low" value="1" <?= $filters['low_only'] ? 'checked' : '' ?>> Ниже минимума</label>
                <button class="btn btn--ghost btn--sm" type="submit"><?= icon('filter') ?> Показать</button>
            </form>
            <div class="toolbar__right">
                <?php if ($canAdjust): ?>
                    <button class="btn btn--primary btn--sm" type="button" data-modal-open="adjust-modal"><?= icon('edit') ?> Изменить остаток</button>
                <?php endif; ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/stock/in')) ?>"><?= icon('truck') ?> Приход</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/products/export')) ?>"><?= icon('download') ?> CSV</a>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Товар</th>
                    <th class="num">Остаток</th>
                    <?php if ($finance): ?>
                        <th class="num">Себестоимость</th>
                        <th class="num">Цена</th>
                        <th class="num">Стоимость</th>
                    <?php endif; ?>
                    <th class="num">Минимум</th>
                    <?php if ($canAdjust): ?><th></th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product):
                    $negative = (int) $product['stock_qty'] < 0;
                    $low = (int) $product['stock_qty'] <= (int) $product['low_stock'];
                    ?>
                    <tr>
                        <td>
                            <div class="product-cell">
                                <?= product_photo($product, 'thumb') ?>
                                <div class="product-cell__text">
                                    <strong><?= e($product['name']) ?></strong>
                                    <span><?= e($product['sku'] ?: '—') ?> · <?= e($product['category']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="num">
                            <span class="stock-pill <?= $negative ? 'stock-pill--out' : ($low ? 'stock-pill--low' : '') ?>"><?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?></span>
                        </td>
                        <?php if ($finance): ?>
                            <td class="num muted"><?= e(money((int) $product['purchase_price_kop'])) ?></td>
                            <td class="num strong"><?= e(money((int) $product['sale_price_kop'])) ?></td>
                            <td class="num"><?= e(money((int) $product['stock_qty'] * (int) $product['purchase_price_kop'])) ?></td>
                        <?php endif; ?>
                        <td class="num muted"><?= (int) $product['low_stock'] ?></td>
                        <?php if ($canAdjust): ?>
                            <td>
                                <div class="row-actions">
                                    <button class="icon-btn" type="button" title="Изменить остаток"
                                            data-stock-adjust="<?= (int) $product['id'] ?>"
                                            data-product-stock="<?= (int) $product['stock_qty'] ?>"><?= icon('edit') ?></button>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <header class="card__head"><div><h3>Движения склада</h3><p>Последние 120 операций</p></div><?= icon('activity', 'icon icon--muted') ?></header>
        <?php if ($movements === []): ?>
            <div class="empty empty--compact"><?= icon('list') ?><p>Движений пока нет</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead>
                    <tr>
                        <th>Когда</th><th>Товар</th><th>Операция</th>
                        <th class="num">Изменение</th><th class="num">Остаток</th>
                        <?php if ($finance): ?><th class="num">Себестоимость</th><?php endif; ?>
                        <th>Сотрудник</th><th>Комментарий</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($movements as $move): ?>
                        <tr>
                            <td class="muted" style="white-space: nowrap;"><?= e(dt_human($move['created_at'])) ?></td>
                            <td><?= e($move['product_name'] ?? '—') ?></td>
                            <td><span class="badge badge--<?= e(movement_tone((string) $move['type'])) ?>"><?= e(movement_label((string) $move['type'])) ?></span></td>
                            <td class="num" style="color: <?= (int) $move['qty_delta'] < 0 ? 'var(--red)' : 'var(--green)' ?>; font-weight: 650;"><?= (int) $move['qty_delta'] > 0 ? '+' : '' ?><?= (int) $move['qty_delta'] ?></td>
                            <td class="num"><?= (int) $move['qty_after'] ?></td>
                            <?php if ($finance): ?><td class="num muted"><?= e(money((int) $move['unit_cost_kop'])) ?></td><?php endif; ?>
                            <td><?= e($move['user_name'] ?? '—') ?></td>
                            <td class="muted"><?= e($move['reason'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php if ($canAdjust): ?>
    <div class="modal" id="adjust-modal" aria-hidden="true">
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="adjust-title">
            <div class="modal__head">
                <div><h3 id="adjust-title">Изменить остаток</h3><p>Укажите фактическое количество</p></div>
                <button type="button" class="icon-btn" data-modal-close aria-label="Закрыть"><?= icon('close') ?></button>
            </div>
            <form method="post" action="<?= e(url('/stock/adjust')) ?>">
                <?= csrf_field() ?>
                <div class="modal__body">
                    <div class="stack">
                        <label class="field">
                            <span class="label">Товар</span>
                            <select class="select" name="product_id" id="adjust-product" required>
                                <option value="">Выберите товар</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= (int) $product['id'] ?>" data-stock="<?= (int) $product['stock_qty'] ?>">
                                        <?= e($product['name']) ?> · сейчас <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span class="label">Фактический остаток</span>
                            <input class="input" type="number" name="qty" id="adjust-qty" required value="0">
                            <span class="hint">Можно указать отрицательное число, если остаток ушёл в минус.</span>
                        </label>
                        <label class="field">
                            <span class="label">Причина</span>
                            <input class="input" type="text" name="reason" maxlength="255" placeholder="Например, пересчёт склада">
                        </label>
                    </div>
                </div>
                <div class="modal__foot">
                    <button class="btn btn--ghost" type="button" data-modal-close>Отмена</button>
                    <button class="btn btn--primary" type="submit">Сохранить остаток</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        (function () {
            var select = document.getElementById('adjust-product');
            if (!select) return;
            document.querySelectorAll('[data-stock-adjust]').forEach(function (button) {
                button.addEventListener('click', function () {
                    select.value = button.getAttribute('data-stock-adjust');
                    var qty = document.getElementById('adjust-qty');
                    if (qty) qty.value = button.getAttribute('data-product-stock') || '0';
                    window.PIZ_openModal('adjust-modal');
                });
            });
            select.addEventListener('change', function () {
                var option = select.options[select.selectedIndex];
                var qty = document.getElementById('adjust-qty');
                if (option && qty) qty.value = option.getAttribute('data-stock') || '0';
            });
        })();
    </script>
<?php endif; ?>
