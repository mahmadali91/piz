<?php
$user = \PIZ\Auth::user();
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
<body class="<?= e($bodyClass ?? '') ?>">
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
<?php if ($user): ?>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="<?= e(url('/')) ?>">
                <span class="brand__mark">P</span>
                <span class="brand__text">
                    <strong><?= e(mb_substr(\PIZ\Settings::get('store_name', 'Киоск PIZ'), 0, 22)) ?></strong>
                    <span>учёт продаж</span>
                </span>
            </a>

            <nav class="nav">
                <div class="nav__label">Работа</div>
                <?php if (\PIZ\Auth::can('dashboard.view')): ?>
                    <a class="nav__item <?= active_class($active ?? '', 'dashboard') ?>" href="<?= e(url('/')) ?>">
                        <?= icon('grid') ?><span>Дашборд</span>
                    </a>
                <?php endif; ?>
                <?php if (\PIZ\Auth::can('pos.use')): ?>
                    <a class="nav__item <?= active_class($active ?? '', 'pos') ?>" href="<?= e(url('/pos')) ?>">
                        <?= icon('cart') ?><span>Касса</span><span class="nav__kbd">N</span>
                    </a>
                <?php endif; ?>

                <div class="nav__label">Магазин</div>
                <?php if (\PIZ\Auth::can('products.view')): ?>
                    <a class="nav__item <?= active_class($active ?? '', 'products') ?>" href="<?= e(url('/products')) ?>">
                        <?= icon('box') ?><span>Товары</span>
                    </a>
                <?php endif; ?>
                <a class="nav__item <?= active_class($active ?? '', 'sales') ?>" href="<?= e(url('/sales')) ?>">
                    <?= icon('receipt') ?><span>Продажи</span>
                </a>

                <?php if (\PIZ\Auth::can('reports.view')): ?>
                    <div class="nav__label">Аналитика</div>
                    <a class="nav__item <?= active_class($active ?? '', 'reports') ?>" href="<?= e(url('/reports')) ?>">
                        <?= icon('chart') ?><span>Отчёты</span>
                    </a>
                <?php endif; ?>

                <div class="nav__label">Управление</div>
                <?php if (\PIZ\Auth::can('users.manage')): ?>
                    <a class="nav__item <?= active_class($active ?? '', 'employees') ?>" href="<?= e(url('/employees')) ?>">
                        <?= icon('users') ?><span>Сотрудники</span>
                    </a>
                <?php endif; ?>
                <?php if (\PIZ\Auth::can('activity.view')): ?>
                    <a class="nav__item <?= active_class($active ?? '', 'activity') ?>" href="<?= e(url('/activity')) ?>">
                        <?= icon('activity') ?><span>Журнал</span>
                    </a>
                <?php endif; ?>
                <a class="nav__item <?= active_class($active ?? '', 'settings') ?>" href="<?= e(url('/settings')) ?>">
                    <?= icon('settings') ?><span>Настройки</span>
                </a>
            </nav>

            <div class="sidebar__foot">
                <div class="sidebar-user">
                    <span class="avatar avatar--sm"><?= e(initials((string) $user['name'])) ?></span>
                    <span class="sidebar-user__meta">
                        <strong><?= e($user['name']) ?></strong>
                        <span><?= e(role_label((string) $user['role'])) ?></span>
                    </span>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="icon-btn icon-btn--dark" title="Выйти" aria-label="Выйти"><?= icon('logout') ?></button>
                    </form>
                </div>
            </div>
        </aside>
        <div class="sidebar-backdrop" data-sidebar-close></div>

        <div class="main">
            <header class="topbar">
                <button type="button" class="icon-btn menu-toggle" data-sidebar-toggle aria-label="Меню"><?= icon('menu') ?></button>
                <div class="topbar__heading">
                    <h1><?= e($title ?? '') ?></h1>
                    <?php if (!empty($subtitle)): ?>
                        <p><?= e($subtitle) ?></p>
                    <?php endif; ?>
                </div>
                <div class="topbar__right">
                    <span class="date-chip"><?= icon('calendar') ?><span><?= date('d.m.Y') ?></span></span>
                    <?php if (\PIZ\Auth::can('pos.use')): ?>
                        <a class="btn btn--primary btn--sm topbar__sale" href="<?= e(url('/pos')) ?>">
                            <?= icon('plus') ?><span>Новая продажа</span>
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <main class="content">
                <?= $content ?>
            </main>
        </div>
    </div>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<?php if (!empty($pageScript)): ?>
    <script src="<?= e(asset($pageScript)) ?>" defer></script>
<?php endif; ?>
</body>
</html>
