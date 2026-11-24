<?php /** @var array $sale */
$storeName = \PIZ\Settings::get('store_name', 'КИОСК ДОНЕР');
$address = \PIZ\Settings::get('store_address', '');
$phone = \PIZ\Settings::get('store_phone', '');
$header = \PIZ\Settings::get('receipt_header', '');
$footer = \PIZ\Settings::get('receipt_footer', '');
$subtotal = 0;
foreach ($sale['items'] as $item) {
    $subtotal += (int) $item['total_kop'];
}
$canceled = ($sale['status'] ?? 'completed') === 'canceled';
?>
<div class="receipt-print">
    <div class="receipt__head">
        <div class="receipt__logo">Д</div>
        <div class="receipt__store"><?= e($storeName) ?></div>
        <?php if ($address !== ''): ?><div class="receipt__muted"><?= e($address) ?></div><?php endif; ?>
        <?php if ($phone !== ''): ?><div class="receipt__muted"><?= e($phone) ?></div><?php endif; ?>
        <?php if ($header !== ''): ?><div class="receipt__note"><?= nl2br(e($header)) ?></div><?php endif; ?>
    </div>

    <?php if ($canceled): ?>
        <div class="receipt__canceled">ОТМЕНЕНА</div>
    <?php endif; ?>

    <div class="receipt__meta">
        <span>Чек № <?= e($sale['number']) ?></span>
        <span><?= e(dt_human($sale['created_at'])) ?></span>
    </div>
    <div class="receipt__meta">
        <span>Кассир: <?= e($sale['cashier_name'] ?? '—') ?></span>
        <span><?= e(mb_strtoupper(\PIZ\Settings::get('currency', '₽'))) ?></span>
    </div>

    <div class="receipt__divider"></div>

    <table class="receipt__items">
        <tbody>
        <?php foreach ($sale['items'] as $item): ?>
            <tr>
                <td class="receipt__item-name" colspan="3"><?= e($item['name']) ?></td>
            </tr>
            <tr>
                <td><?= (int) $item['qty'] ?> × <?= e(kop_to_input((int) $item['price_kop'])) ?></td>
                <td class="receipt__qty"></td>
                <td class="receipt__sum"><?= e(kop_to_input((int) $item['total_kop'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="receipt__divider"></div>

    <div class="receipt__totals">
        <div><span>Подытог</span><span><?= e(money($subtotal)) ?></span></div>
        <?php if ((int) $sale['discount_kop'] > 0): ?>
            <div><span>Скидка</span><span>−<?= e(money((int) $sale['discount_kop'])) ?></span></div>
        <?php endif; ?>
        <div class="receipt__grand"><span>ИТОГО</span><span><?= e(money((int) $sale['total_kop'])) ?></span></div>
        <div><span>Оплата</span><span><?= e(payment_label((string) $sale['payment_method'])) ?></span></div>
        <?php if ($sale['payment_method'] === 'cash' && (int) $sale['received_kop'] > 0): ?>
            <div><span>Получено</span><span><?= e(money((int) $sale['received_kop'])) ?></span></div>
            <div><span>Сдача</span><span><?= e(money((int) $sale['change_kop'])) ?></span></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($sale['customer']) || !empty($sale['comment'])): ?>
        <div class="receipt__divider"></div>
        <?php if (!empty($sale['customer'])): ?><div class="receipt__note">Покупатель: <?= e($sale['customer']) ?></div><?php endif; ?>
        <?php if (!empty($sale['comment'])): ?><div class="receipt__note"><?= e($sale['comment']) ?></div><?php endif; ?>
    <?php endif; ?>

    <div class="receipt__divider"></div>
    <div class="receipt__thanks"><?= e($footer !== '' ? $footer : 'Спасибо за покупку!') ?></div>
    <div class="receipt__brand">ДОНЕР · <?= e(date('Y')) ?></div>
</div>
