<?php
?>
<div class="page">
    <?php \PIZ\View::partial('partials/stock-tabs', ['stockTab' => 'prices']); ?>

    <section class="card">
        <header class="card__head">
            <div><h3>Массовое изменение цен</h3><p>Применится ко всем активным товарам</p></div>
            <?= icon('tag', 'icon icon--muted') ?>
        </header>
        <form method="post" action="<?= e(url('/prices/bulk')) ?>" class="toolbar" style="align-items: flex-end;">
            <?= csrf_field() ?>
            <div class="toolbar__left" style="align-items: flex-end;">
                <label class="field" style="min-width: 170px;">
                    <span class="label">Что менять</span>
                    <select class="select" name="target">
                        <option value="sale">Цена продажи</option>
                        <option value="purchase">Себестоимость</option>
                        <option value="both">И то, и другое</option>
                    </select>
                </label>
                <label class="field" style="min-width: 160px;">
                    <span class="label">Как</span>
                    <select class="select" name="mode">
                        <option value="percent">На процент</option>
                        <option value="amount">На сумму, ₽</option>
                        <option value="set">Установить значение</option>
                    </select>
                </label>
                <label class="field" style="min-width: 140px;">
                    <span class="label">Значение</span>
                    <input class="input" type="text" name="value" inputmode="decimal" placeholder="Например, 10">
                </label>
            </div>
            <div class="toolbar__right">
                <button class="btn btn--primary" type="submit"><?= icon('refresh') ?> Применить ко всем</button>
            </div>
        </form>
        <p class="hint" style="margin-top: 8px;">Процент может быть отрицательным: `-10` снизит цену на 10%. Себестоимость — средневзвешенная по партиям.</p>
    </section>

    <section class="card">
        <header class="card__head">
            <div><h3>Цены товаров</h3><p>Можно изменить каждую позицию отдельно</p></div>
            <span class="badge badge--gray"><?= count($products) ?> позиций</span>
        </header>
        <form method="post" action="<?= e(url('/prices/save')) ?>">
            <?= csrf_field() ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Товар</th>
                        <th class="num">Себестоимость, ₽</th>
                        <th class="num">Цена продажи, ₽</th>
                        <th class="num">Маржа</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product):
                        $purchase = (int) $product['purchase_price_kop'];
                        $sale = (int) $product['sale_price_kop'];
                        $margin = $sale > 0 ? round(($sale - $purchase) / $sale * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <?= product_photo($product, 'thumb') ?>
                                    <div class="product-cell__text">
                                        <strong><?= e($product['name']) ?></strong>
                                        <span><?= e($product['sku'] ?: '—') ?> · остаток <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="num">
                                <input class="input input--price" type="text" inputmode="decimal" name="prices[<?= (int) $product['id'] ?>][purchase]" value="<?= e(kop_to_input($purchase)) ?>">
                            </td>
                            <td class="num">
                                <input class="input input--price" type="text" inputmode="decimal" name="prices[<?= (int) $product['id'] ?>][sale]" value="<?= e(kop_to_input($sale)) ?>">
                            </td>
                            <td class="num <?= $margin < 0 ? 'stat__value--red' : '' ?>" style="color: <?= $margin < 0 ? 'var(--red)' : 'var(--green)' ?>; font-weight: 650;"><?= e(number_format($margin, 1, ',', ' ')) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions" style="margin-top: 14px;">
                <button class="btn btn--primary" type="submit"><?= icon('check') ?> Сохранить цены</button>
            </div>
        </form>
    </section>
</div>
