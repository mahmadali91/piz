<?php
declare(strict_types=1);

define('PIZ_ENTRY', true);

require __DIR__ . '/src/bootstrap.php';

require __DIR__ . '/src/routes.php';

// Пока не настроена база или не создан администратор — ведём в установщик.
$path = \PIZ\Router::currentPath();
if (!str_starts_with($path, '/setup') && $path !== '/healthz') {
    if (!\PIZ\Install::isConfigured()) {
        redirect('/setup');
    }
    if (!\PIZ\Install::hasUsers()) {
        redirect('/setup/admin');
    }
}

\PIZ\Router::dispatch();
