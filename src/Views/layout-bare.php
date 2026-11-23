<?php
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#141821">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'PIZ') ?> — <?= e(\PIZ\Settings::get('store_name', 'Киоск PIZ')) ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='16' fill='%23f26b21'/%3E%3Ctext x='32' y='45' font-family='Arial,sans-serif' font-size='38' font-weight='bold' fill='white' text-anchor='middle'%3EP%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="bare-body">
<?php require PIZ_ROOT . '/src/Views/partials/icons.php'; ?>
<div class="toasts" id="toasts" aria-live="polite">
    <?php foreach (flashes() as $item): ?>
        <div class="toast toast--<?= e($item['type']) ?>" data-toast>
            <span class="toast__icon"><?= $item['type'] === 'success' ? icon('check') : icon('alert') ?></span>
            <span class="toast__text"><?= e($item['message']) ?></span>
            <button type="button" class="toast__close" data-toast-close aria-label="Закрыть"><?= icon('close') ?></button>
        </div>
    <?php endforeach; ?>
</div>
<?= $content ?>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
