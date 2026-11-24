<?php
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#141821">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'ДОНЕР') ?> — <?= e(\PIZ\Settings::get('store_name', 'КИОСК ДОНЕР')) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/icons/favicon-32.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('assets/icons/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="ДОНЕР">
    <meta name="mobile-web-app-capable" content="yes">
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
<?php require PIZ_ROOT . '/src/Views/partials/install.php'; ?>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
