<?php
/** @var array $sale */
$subtotal = 0;
foreach ($sale['items'] as $item) {
    $subtotal += (int) $item['total_kop'];
}
$profit = (int) $sale['total_kop'] - (int) $sale['cost_kop'];
$isCanceled = ($sale['status'] ?? '') === 'canceled';
?>
<div class="page">
    <div class="toolbar">
        <div class="toolbar__left">
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/sales')) ?>"><?= icon('chevron-left', 'icon icon--xs') ?> К продажам</a>
            <div>
                <h2 style="font-size: 20px;">Чек <?= e($sale['number']) ?></h2>
                <div class="muted" style="font-size: 12px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 2px;">
                    <?= e(dt_human($sale['created_at'])) ?>
                    <span class="dot"></span>
                    <?= e($sale['cashier_name'] ?? '—') ?>
                    <?= sale_status_badge((string) $sale['status']) ?>
                </div>
            </div>
        </div>
        <div class="toolbar__right">
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/sales/' . $sale['id'] . '/pdf')) ?>"><?= icon('download') ?> Скачать PDF</a>
            <button class="btn btn--ghost btn--sm" type="button" data-print-receipt><?= icon('print') ?> Печать</button>
            <?php if ($canCancel && !$isCanceled): ?>
                <form method="post" action="<?= e(url('/sales/' . $sale['id'] . '/cancel')) ?>" data-confirm="Отменить продажу и вернуть товары на склад? Действие отразится в отчётах.">
                    <?= csrf_field() ?>
                    <button class="btn btn--danger btn--sm" type="submit"><?= icon('close') ?> Отменить продажу</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isCanceled): ?>
        <div class="alert alert--error">
            <?= icon('alert') ?>
            <span>Продажа отменена <?= e(dt_human($sale['canceled_at'])) ?>. Товары возвращены на склад, сумма не входит в выручку.</span>
        </div>
    <?php endif; ?>

    <div class="settings-grid">
        <div class="settings-stack">
            <section class="card">
                <header class="card__head">
                    <div>
                        <h3>Состав продажи</h3>
                        <p><?= count($sale['items']) ?> <?= plural(count($sale['items']), 'позиция', 'позиции', 'позиций') ?> в чеке</p>
                    </div>
                    <?= payment_badge((string) $sale['payment_method']) ?>
                </header>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Товар</th>
                            <th class="num">Кол-во</th>
                            <th class="num">Цена</th>
                            <th class="num">Сумма</th>
                            <?php if ($finance): ?><th class="num">Себестоимость</th><?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sale['items'] as $item): ?>
                            <tr>
                                <td>
                                    <div class="product-cell__text">
                                        <strong><?= e($item['name']) ?></strong>
                                        <span>Позиция #<?= (int) $item['id'] ?></span>
                                    </div>
                                </td>
                                <td class="num"><?= (int) $item['qty'] ?></td>
                                <td class="num"><?= e(money((int) $item['price_kop'])) ?></td>
                                <td class="num strong"><?= e(money((int) $item['total_kop'])) ?></td>
                                <?php if ($finance): ?>
                                    <td class="num muted"><?= e(money((int) $item['cost_kop'] * (int) $item['qty'])) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 14px;">
                    <div style="min-width: 260px; display: flex; flex-direction: column; gap: 7px;">
                        <div class="cart-line"><span>Подытог</span><strong><?= e(money($subtotal)) ?></strong></div>
                        <?php if ((int) $sale['discount_kop'] > 0): ?>
                            <div class="cart-line"><span>Скидка</span><strong style="color: var(--red);">−<?= e(money((int) $sale['discount_kop'])) ?></strong></div>
                        <?php endif; ?>
                        <div class="cart-line cart-line--total"><span>Итого</span><strong><?= e(money((int) $sale['total_kop'])) ?></strong></div>
                        <?php if ($finance): ?>
                            <div class="cart-line"><span>Себестоимость</span><strong><?= e(money((int) $sale['cost_kop'])) ?></strong></div>
                            <div class="cart-line" style="color: var(--green);"><span>Прибыль</span><strong><?= e(money($profit)) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($sale['payment_method'] === 'cash'): ?>
                            <div class="cart-line"><span>Получено</span><strong><?= e(money((int) $sale['received_kop'])) ?></strong></div>
                            <div class="cart-line"><span>Сдача</span><strong><?= e(money((int) $sale['change_kop'])) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($sale['customer']) || !empty($sale['comment'])): ?>
                    <div class="form-section">
                        <?php if (!empty($sale['customer'])): ?>
                            <div class="cart-line"><span>Покупатель</span><strong><?= e($sale['customer']) ?></strong></div>
                        <?php endif; ?>
                        <?php if (!empty($sale['comment'])): ?>
                            <div class="cart-line" style="margin-top: 5px;"><span>Комментарий</span><strong style="font-weight: 500; text-align: right;"><?= e($sale['comment']) ?></strong></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <header class="card__head">
                    <div>
                        <h3>Информация о чеке</h3>
                        <p>Кто и когда оформил продажу</p>
                    </div>
                    <?= icon('receipt', 'icon icon--muted') ?>
                </header>
                <div class="health-list">
                    <div class="health-row"><span>Номер</span><strong><?= e($sale['number']) ?></strong></div>
                    <div class="health-row"><span>Дата и время</span><strong><?= e(dt_human($sale['created_at'])) ?></strong></div>
                    <div class="health-row"><span>Кассир</span><strong><?= e($sale['cashier_name'] ?? '—') ?></strong></div>
                    <div class="health-row"><span>Способ оплаты</span><strong><?= e(payment_label((string) $sale['payment_method'])) ?></strong></div>
                    <div class="health-row"><span>Статус</span><strong><?= $isCanceled ? 'Отменена' : 'Завершена' ?></strong></div>
                    <?php if ($isCanceled): ?>
                        <div class="health-row"><span>Отменена</span><strong><?= e(dt_human($sale['canceled_at'])) ?></strong></div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="settings-stack">
            <section class="card">
                <header class="card__head">
                    <div>
                        <h3>Чек для печати</h3>
                        <p>Формат 80 мм</p>
                    </div>
                    <div class="row-actions"><a class="btn btn--soft btn--sm" href="<?= e(url('/sales/' . $sale['id'] . '/pdf')) ?>"><?= icon('download') ?> Скачать PDF</a><button class="btn btn--soft btn--sm" type="button" data-print-receipt><?= icon('print') ?> Печать</button></div>
                </header>
                <?php \PIZ\View::partial('partials/receipt', ['sale' => $sale]); ?>
            </section>
        </div>
    </div>
</div>
