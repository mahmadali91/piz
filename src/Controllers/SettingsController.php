<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Database;
use PIZ\Models\Product;
use PIZ\Models\Sale;
use PIZ\Models\User;
use PIZ\Settings;
use PIZ\View;

final class SettingsController
{
    public function index(): void
    {
        Auth::requireLogin();

        $health = [
            'php' => PHP_VERSION,
            'mysql' => (string) Database::fetchValue('SELECT VERSION()'),
            'tables' => [],
            'uploads_writable' => is_dir(PIZ_ROOT . '/uploads') && is_writable(PIZ_ROOT . '/uploads'),
            'config_writable' => is_writable(PIZ_ROOT . '/config'),
            'products' => (int) Database::fetchValue('SELECT COUNT(*) FROM products'),
            'users' => User::count(),
            'sales' => (int) Database::fetchValue('SELECT COUNT(*) FROM sales'),
            'last_sale' => Database::fetchValue('SELECT created_at FROM sales ORDER BY id DESC LIMIT 1'),
            'last_backup' => null,
        ];
        foreach (['users', 'products', 'sales', 'sale_items', 'stock_movements', 'settings', 'audit_log'] as $table) {
            $health['tables'][$table] = Database::tableExists($table);
        }

        View::render('settings', [
            'title' => 'Настройки',
            'active' => 'settings',
            'user' => Auth::user(),
            'settings' => Settings::all(),
            'health' => $health,
            'canManage' => Auth::can('settings.manage'),
        ]);
    }

    public function save(): void
    {
        Auth::requirePermission('settings.manage');

        $storeName = trim((string) ($_POST['store_name'] ?? ''));
        $address = trim((string) ($_POST['store_address'] ?? ''));
        $phone = trim((string) ($_POST['store_phone'] ?? ''));
        $currency = trim((string) ($_POST['currency'] ?? '₽'));
        $receiptHeader = trim((string) ($_POST['receipt_header'] ?? ''));
        $receiptFooter = trim((string) ($_POST['receipt_footer'] ?? ''));
        $lowStock = (int) ($_POST['low_stock_default'] ?? 3);
        $allowNegative = isset($_POST['allow_negative_stock']) ? '1' : '0';

        $errors = [];
        if ($storeName === '') {
            $errors[] = 'Укажите название киоска.';
        }
        if (mb_strlen($storeName) > 80) {
            $errors[] = 'Название киоска слишком длинное.';
        }
        if (mb_strlen($currency) > 8 || $currency === '') {
            $currency = '₽';
        }
        if ($lowStock < 0) {
            $lowStock = 0;
        }
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            redirect('/settings');
        }

        Settings::set('store_name', mb_substr($storeName, 0, 80));
        Settings::set('store_address', mb_substr($address, 0, 160));
        Settings::set('store_phone', mb_substr($phone, 0, 40));
        Settings::set('currency', $currency);
        Settings::set('receipt_header', mb_substr($receiptHeader, 0, 500));
        Settings::set('receipt_footer', mb_substr($receiptFooter, 0, 500));
        Settings::set('low_stock_default', (string) min(100000, $lowStock));
        Settings::set('allow_negative_stock', $allowNegative);

        Audit::log('settings.update', ['store_name' => $storeName]);
        flash('success', 'Настройки сохранены.');
        redirect('/settings');
    }

    public function password(): void
    {
        Auth::requireLogin();
        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirm'] ?? '');

        $user = Auth::user();
        if (!$user || !password_verify($current, (string) $user['password_hash'])) {
            flash('error', 'Текущий пароль указан неверно.');
            redirect('/settings');
        }
        $errors = Auth::passwordErrors($password, $confirmation);
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            redirect('/settings');
        }

        User::setPassword((int) $user['id'], $password);
        Audit::log('password.change', ['id' => (int) $user['id']]);
        flash('success', 'Пароль изменён.');
        redirect('/settings');
    }
}
