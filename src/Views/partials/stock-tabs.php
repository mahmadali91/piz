<?php
$tabs = [];
if (\PIZ\Auth::can('products.view')) {
    $tabs[] = ['key' => 'products', 'label' => 'Товары', 'url' => '/products', 'icon' => 'box'];
}
if (\PIZ\Auth::can('stock.manage')) {
    $tabs[] = ['key' => 'in', 'label' => 'Приход', 'url' => '/stock/in', 'icon' => 'truck'];
    $tabs[] = ['key' => 'stock', 'label' => 'Остаток', 'url' => '/stock', 'icon' => 'list'];
}
if (\PIZ\Auth::can('writeoff.manage')) {
    $tabs[] = ['key' => 'out', 'label' => 'Расход', 'url' => '/stock/out', 'icon' => 'logout'];
}
if (\PIZ\Auth::can('prices.manage')) {
    $tabs[] = ['key' => 'prices', 'label' => 'Цены', 'url' => '/prices', 'icon' => 'tag'];
}
?>
<div class="subtabs" role="navigation" aria-label="Склад">
    <?php foreach ($tabs as $tab): ?>
        <a class="subtab <?= ($stockTab ?? '') === $tab['key'] ? 'is-active' : '' ?>" href="<?= e(url($tab['url'])) ?>">
            <?= icon($tab['icon'], 'icon icon--xs') ?>
            <span><?= e($tab['label']) ?></span>
        </a>
    <?php endforeach; ?>
</div>
