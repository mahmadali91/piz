<?php
declare(strict_types=1);

define('PIZ_ROOT', dirname(__DIR__));
$versionFile = PIZ_ROOT . '/VERSION';
define('PIZ_VERSION', is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '1.23.26');

spl_autoload_register(static function (string $class): void {
    $prefix = 'PIZ\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = PIZ_ROOT . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once PIZ_ROOT . '/src/helpers.php';

date_default_timezone_set(getenv('PIZ_TZ') ?: 'Europe/Moscow');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$logDir = PIZ_ROOT . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
ini_set('error_log', $logDir . '/php-error.log');

// HTTPS определяем с учётом прокси хостинга.
$https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');

// Принудительный HTTPS для боевого домена (локально и в CLI не вмешиваемся).
$host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
if (!$https && PHP_SAPI !== 'cli' && in_array($host, ['piz.itop7.ru', 'www.piz.itop7.ru'], true)) {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

$cookiePath = base_url() !== '' ? base_url() . '/' : '/';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookiePath,
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('piz_session');
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$configFile = PIZ_ROOT . '/config/config.php';
$config = null;
$dbError = null;

if (is_file($configFile)) {
    /** @var array $config */
    $config = require $configFile;
    if (!is_array($config)) {
        $config = null;
        $dbError = 'Файл config/config.php повреждён.';
    } else {
        try {
            \PIZ\Database::connect($config['db'] ?? []);
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
            error_log('[PIZ] DB connection failed: ' . $e->getMessage());
        }
    }
}

$GLOBALS['piz_config'] = $config;
$GLOBALS['piz_db_error'] = $dbError;

set_exception_handler(static function (\Throwable $e): void {
    error_log('[PIZ] Unhandled: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (is_json_request()) {
        json_response(['ok' => false, 'error' => 'Внутренняя ошибка сервера. Попробуйте ещё раз.'], 500);
    }
    http_response_code(500);
    $debug = (bool) ($GLOBALS['piz_config']['debug'] ?? false);
    \PIZ\View::render('errors/500', [
        'title' => 'Ошибка сервера',
        'message' => $debug ? $e->getMessage() : null,
        'showLayout' => \PIZ\Auth::check(),
    ], \PIZ\Auth::check() ? 'layout' : 'layout-bare');
    exit;
});

// Базовые заголовки безопасности.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; font-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'");
if ($https) {
    header('Strict-Transport-Security: max-age=31536000');
}
