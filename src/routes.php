<?php
declare(strict_types=1);

use PIZ\Controllers\ActivityController;
use PIZ\Controllers\AuthController;
use PIZ\Controllers\DashboardController;
use PIZ\Controllers\EmployeesController;
use PIZ\Controllers\PosController;
use PIZ\Controllers\ProductsController;
use PIZ\Controllers\ReportsController;
use PIZ\Controllers\SaleController;
use PIZ\Controllers\SettingsController;
use PIZ\Controllers\SetupController;
use PIZ\Database;
use PIZ\Router;

// Установка
Router::get('/setup', [SetupController::class, 'index']);
Router::post('/setup/db', [SetupController::class, 'saveDatabase']);
Router::get('/setup/admin', [SetupController::class, 'adminForm']);
Router::post('/setup/admin', [SetupController::class, 'createAdmin']);

// Авторизация
Router::get('/login', [AuthController::class, 'showLogin']);
Router::post('/login', [AuthController::class, 'login']);
Router::post('/logout', [AuthController::class, 'logout']);

// Рабочее место
Router::get('/', [DashboardController::class, 'index']);
Router::get('/dashboard', [DashboardController::class, 'index']);
Router::get('/pos', [PosController::class, 'index']);
Router::post('/api/sales', [SaleController::class, 'createApi']);

// Товары
Router::get('/products', [ProductsController::class, 'index']);
Router::post('/products/save', [ProductsController::class, 'save']);
Router::post('/products/toggle', [ProductsController::class, 'toggle']);
Router::post('/products/stock', [ProductsController::class, 'stock']);
Router::post('/products/import', [ProductsController::class, 'import']);
Router::get('/products/export', [ProductsController::class, 'export']);
Router::get('/products/template', [ProductsController::class, 'template']);

// Продажи
Router::get('/sales', [SaleController::class, 'index']);
Router::get('/sales/export', [SaleController::class, 'export']);
Router::get('/sales/{id}', [SaleController::class, 'show']);
Router::post('/sales/{id}/cancel', [SaleController::class, 'cancel']);

// Отчёты
Router::get('/reports', [ReportsController::class, 'index']);
Router::get('/reports/export', [ReportsController::class, 'export']);

// Сотрудники
Router::get('/employees', [EmployeesController::class, 'index']);
Router::post('/employees/save', [EmployeesController::class, 'save']);
Router::post('/employees/toggle', [EmployeesController::class, 'toggle']);
Router::post('/employees/password', [EmployeesController::class, 'password']);

// Настройки
Router::get('/settings', [SettingsController::class, 'index']);
Router::post('/settings/save', [SettingsController::class, 'save']);
Router::post('/settings/password', [SettingsController::class, 'password']);

// Журнал
Router::get('/activity', [ActivityController::class, 'index']);

// Служебная проверка доступности
Router::get('/healthz', static function (): void {
    header('Content-Type: application/json; charset=utf-8');
    $ok = Database::isConnected();
    http_response_code($ok ? 200 : 503);
    echo json_encode([
        'ok' => $ok,
        'app' => 'piz',
        'version' => PIZ_VERSION,
        'time' => now(),
    ]);
});
