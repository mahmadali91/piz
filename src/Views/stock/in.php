<?php
/** @var array $receiptTotals */
?>
<div class="page">
    <?php \PIZ\View::partial('partials/stock-tabs', ['stockTab' => 'in']); ?>

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
        <form method="post" action="<?= e(url('/stock/in')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid form-grid--3">
                <label class="field span-2">
                    <span class="label">Товар</span>
                    <select class="select" name="product_id" required>
                        <option value="">Выберите товар</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int) $product['id'] ?>">
                                <?= e($product['name']) ?> · сейчас <?= (int) $product['stock_qty'] ?> <?= e($product['unit']) ?>
                                <?php if ($finance): ?> · себестоимость <?= e(kop_to_input((int) $product['purchase_price_kop'])) ?> ₽<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span class="label">Количество</span>
                    <input class="input" type="number" name="qty" min="1" max="100000" required placeholder="Например, 10">
                </label>
                <?php if ($canPrices): ?>
                    <label class="field">
                        <span class="label">Себестоимость за единицу, ₽</span>
                        <input class="input" type="text" name="unit_cost" inputmode="decimal" placeholder="Цена этой партии">
                        <span class="hint">Если пусто — останется текущая средняя цена.</span>
                    </label>
                <?php endif; ?>
                <label class="field <?= $canPrices ? '' : 'span-2' ?>">
                    <span class="label">Комментарий</span>
                    <input class="input" type="text" name="comment" maxlength="255" placeholder="Поставщик, накладная…">
                </label>
            </div>
            <?php if (!$canPrices): ?>
                <div class="alert alert--info" style="margin-top: 12px;"><?= icon('alert') ?><span>Себестоимость берётся из карточки товара. Менять цены может администратор с правом «Изменение цен».</span></div>
            <?php endif; ?>
            <div class="form-actions" style="margin-top: 8px;">
                <button class="btn btn--primary" type="submit"><?= icon('plus') ?> Оприходовать</button>
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
</div>
