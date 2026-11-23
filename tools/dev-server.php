<?php
/**
 * Маршрутизатор для локального сервера PHP.
 *
 * Запуск из корня проекта:
 *   php -S 127.0.0.1:8080 tools/dev-server.php
 */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = dirname(__DIR__) . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require dirname(__DIR__) . '/index.php';
