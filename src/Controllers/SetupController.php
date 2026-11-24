<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Database;
use PIZ\Install;
use PIZ\Models\User;
use PIZ\Permissions;
use PIZ\Settings;
use PIZ\View;

final class SetupController
{
    public function index(): void
    {
        if (Install::isConfigured()) {
            if (Install::hasUsers()) {
                redirect('/login');
            }
            redirect('/setup/admin');
        }

        $defaults = ['host' => 'localhost', 'port' => '3306', 'name' => 'mahmadgv_piz', 'user' => 'mahmadgv_piz'];
        $configFile = Install::configFile();
        if (is_file($configFile)) {
            $config = require $configFile;
            if (is_array($config['db'] ?? null)) {
                $defaults = array_merge($defaults, array_map('strval', $config['db']));
                unset($defaults['pass'], $defaults['charset']);
            }
        }

        View::render('setup/index', [
            'title' => 'Установка',
            'requirements' => Install::requirements(),
            'requirementsOk' => Install::requirementsOk(),
            'old' => $defaults,
            'error' => $GLOBALS['piz_db_error'] ?? null,
            'showLayout' => false,
        ], 'layout-bare');
    }

    public function saveDatabase(): void
    {
        if (Install::isConfigured() && Install::hasUsers()) {
            redirect('/login');
        }

        $db = [
            'host' => trim((string) ($_POST['db_host'] ?? '')),
            'port' => trim((string) ($_POST['db_port'] ?? '3306')),
            'name' => trim((string) ($_POST['db_name'] ?? '')),
            'user' => trim((string) ($_POST['db_user'] ?? '')),
            'pass' => (string) ($_POST['db_pass'] ?? ''),
        ];
        if ($db['host'] === '') {
            $db['host'] = 'localhost';
        }
        if ($db['port'] === '') {
            $db['port'] = '3306';
        }

        $errors = [];
        if ($db['name'] === '') {
            $errors[] = 'Укажите имя базы данных.';
        }
        if ($db['user'] === '') {
            $errors[] = 'Укажите пользователя базы данных.';
        }

        $manualConfig = null;
        if ($errors === []) {
            try {
                $test = new \PDO(
                    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
                    $db['user'],
                    $db['pass'],
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 6]
                );
                $test->exec('SET NAMES utf8mb4');

                try {
                    Install::writeConfig($db);
                } catch (\Throwable $e) {
                    $manualConfig = Install::configContent($db);
                    throw new \RuntimeException($e->getMessage() . ' Скопируйте код ниже в файл config/config.php на хостинге.');
                }

                Database::connect($db);
                Install::migrate();
                try {
                    $seed = Install::seedCatalog();
                    if ($seed !== null) {
                        $_SESSION['setup_seed'] = $seed;
                    }
                } catch (\Throwable $seedError) {
                    error_log('[PIZ] seed failed: ' . $seedError->getMessage());
                    flash('error', 'Таблицы созданы, но каталог товаров не загрузился: ' . $seedError->getMessage() . ' Загрузите товары вручную.');
                }

                Audit::log('install.database', ['host' => $db['host'], 'name' => $db['name']]);
                redirect('/setup/admin');
            } catch (\Throwable $e) {
                $errors[] = 'Не удалось подключиться к базе: ' . $e->getMessage();
            }
        }

        View::render('setup/index', [
            'title' => 'Установка',
            'requirements' => Install::requirements(),
            'requirementsOk' => Install::requirementsOk(),
            'old' => $db,
            'errors' => $errors,
            'manualConfig' => $manualConfig,
            'showLayout' => false,
        ], 'layout-bare');
    }

    public function adminForm(): void
    {
        if (!Install::isConfigured()) {
            redirect('/setup');
        }
        if (Install::hasUsers()) {
            redirect('/login');
        }

        View::render('setup/admin', [
            'title' => 'Создание администратора',
            'seed' => $_SESSION['setup_seed'] ?? null,
            'old' => ['name' => '', 'login' => 'admin', 'store_name' => 'КИОСК ДОНЕР'],
            'showLayout' => false,
        ], 'layout-bare');
    }

    public function createAdmin(): void
    {
        if (!Install::isConfigured()) {
            redirect('/setup');
        }
        if (Install::hasUsers()) {
            redirect('/login');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $login = Auth::normalizeLogin((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirm'] ?? '');
        $storeName = trim((string) ($_POST['store_name'] ?? ''));

        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors[] = 'Укажите имя (минимум 2 символа).';
        }
        if (!preg_match('/^[a-z0-9._-]{3,32}$/', $login)) {
            $errors[] = 'Логин: 3–32 символа, латиница, цифры, точка, дефис или подчёркивание.';
        }
        $errors = array_merge($errors, Auth::passwordErrors($password, $confirmation));
        if (User::loginExists($login)) {
            $errors[] = 'Такой логин уже занят.';
        }

        if ($errors !== []) {
            View::render('setup/admin', [
                'title' => 'Создание администратора',
                'seed' => $_SESSION['setup_seed'] ?? null,
                'old' => ['name' => $name, 'login' => $login, 'store_name' => $storeName],
                'errors' => $errors,
                'showLayout' => false,
            ], 'layout-bare');
            return;
        }

        $id = User::create([
            'login' => $login,
            'name' => $name,
            'password' => $password,
            'role' => 'admin',
            'permissions' => Permissions::all(),
            'active' => true,
        ]);

        if ($storeName !== '') {
            Settings::set('store_name', mb_substr($storeName, 0, 80));
        }

        $user = User::find($id);
        if ($user) {
            Auth::loginUser($user);
        }
        Audit::log('install.admin', ['login' => $login]);
        unset($_SESSION['setup_seed']);
        flash('success', 'Магазин готов к работе. Данные товаров загружены из файла.');
        redirect('/');
    }
}
