<?php
declare(strict_types=1);

use PIZ\Controllers\ActivityController;
use PIZ\Controllers\AuthController;
use PIZ\Controllers\DashboardController;
use PIZ\Controllers\InventoryController;
use PIZ\Controllers\KnowledgeController;
use PIZ\Controllers\EmployeesController;
use PIZ\Controllers\PasswordResetController;
use PIZ\Controllers\PosController;
use PIZ\Controllers\ProductsController;
use PIZ\Controllers\ReportsController;
use PIZ\Controllers\SaleController;
use PIZ\Controllers\PricesController;
use PIZ\Controllers\SettingsController;
use PIZ\Controllers\SetupController;
use PIZ\Controllers\ShiftController;
use PIZ\Controllers\StockController;
use PIZ\Controllers\SupplierController;
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

// Восстановление пароля
Router::get('/forgot-password', [PasswordResetController::class, 'showRequest']);
Router::post('/forgot-password', [PasswordResetController::class, 'send']);
Router::get('/reset-password', [PasswordResetController::class, 'showReset']);
Router::post('/reset-password', [PasswordResetController::class, 'reset']);

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

// Склад: приход, расход, остаток, цены
Router::get('/stock', [StockController::class, 'index']);
Router::get('/stock/in', [StockController::class, 'in']);
Router::post('/stock/in', [StockController::class, 'inStore']);
Router::get('/stock/out', [StockController::class, 'out']);
Router::post('/stock/out', [StockController::class, 'outStore']);
Router::post('/stock/adjust', [StockController::class, 'adjust']);
Router::get('/prices', [PricesController::class, 'index']);
Router::post('/prices/save', [PricesController::class, 'save']);
Router::post('/prices/bulk', [PricesController::class, 'bulk']);

// Поставщики и накладные
Router::get('/suppliers', [SupplierController::class, 'index']);
Router::post('/suppliers/save', [SupplierController::class, 'save']);
Router::get('/suppliers/invoices', [SupplierController::class, 'invoices']);
Router::get('/suppliers/invoices/create', [SupplierController::class, 'createInvoiceForm']);
Router::post('/suppliers/invoices', [SupplierController::class, 'storeInvoice']);
Router::get('/suppliers/invoices/{id}', [SupplierController::class, 'invoice']);
Router::post('/suppliers/invoices/{id}/pay', [SupplierController::class, 'pay']);
Router::get('/suppliers/invoices/{id}/pdf', [SupplierController::class, 'pdf']);
Router::get('/suppliers/{id}', [SupplierController::class, 'show']);

// Инвентаризация
Router::get('/inventory', [InventoryController::class, 'index']);
Router::post('/inventory/create', [InventoryController::class, 'create']);
Router::get('/inventory/{id}', [InventoryController::class, 'show']);
Router::post('/inventory/{id}/save', [InventoryController::class, 'save']);
Router::post('/inventory/{id}/complete', [InventoryController::class, 'complete']);
Router::get('/inventory/{id}/pdf', [InventoryController::class, 'pdf']);

// Смены
Router::get('/shift', [ShiftController::class, 'index']);
Router::post('/shift/open', [ShiftController::class, 'open']);
Router::post('/shift/close', [ShiftController::class, 'close']);
Router::get('/shifts', [ShiftController::class, 'history']);
Router::get('/shifts/{id}', [ShiftController::class, 'report']);
Router::get('/shifts/{id}/pdf', [ShiftController::class, 'pdf']);
Router::get('/shifts/{id}/export', [ShiftController::class, 'export']);

// Продажи
Router::get('/sales', [SaleController::class, 'index']);
Router::get('/sales/export', [SaleController::class, 'export']);
Router::get('/sales/{id}', [SaleController::class, 'show']);
Router::get('/sales/{id}/pdf', [SaleController::class, 'pdf']);
Router::post('/sales/{id}/cancel', [SaleController::class, 'cancel']);

// Отчёты
Router::get('/reports', [ReportsController::class, 'index']);
Router::get('/reports/pdf', [ReportsController::class, 'pdf']);
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

// База знаний
Router::get('/knowledge', [KnowledgeController::class, 'index']);
Router::get('/knowledge/image/{name}', [KnowledgeController::class, 'image']);

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
