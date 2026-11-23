<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Models\User;
use PIZ\Permissions;
use PIZ\View;

final class EmployeesController
{
    private const ROLES = ['admin', 'manager', 'cashier', 'custom'];

    public function index(): void
    {
        Auth::requirePermission('users.manage');

        View::render('employees', [
            'title' => 'Сотрудники',
            'active' => 'employees',
            'user' => Auth::user(),
            'users' => User::all(),
            'permissionGroups' => Permissions::GROUPS,
            'rolePresets' => Permissions::ROLES,
            'currentUserId' => Auth::id(),
        ]);
    }

    public function save(): void
    {
        Auth::requirePermission('users.manage');

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id > 0 ? User::find($id) : null;
        if ($id > 0 && !$existing) {
            flash('error', 'Сотрудник не найден.');
            redirect('/employees');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $login = Auth::normalizeLogin((string) ($_POST['login'] ?? ''));
        $role = (string) ($_POST['role'] ?? 'cashier');
        if (!in_array($role, self::ROLES, true)) {
            $role = 'cashier';
        }
        $permissions = Permissions::sanitize((array) ($_POST['permissions'] ?? []));
        if ($role === 'admin') {
            $permissions = Permissions::all();
        } elseif ($role !== 'custom' && $permissions === []) {
            $permissions = Permissions::forRole($role);
        }
        $active = isset($_POST['active']);
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Имя: от 2 до 120 символов.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,32}$/', $login)) {
            $errors[] = 'Логин: 3–32 символа, латиница, цифры, точка, дефис или подчёркивание.';
        }
        if (User::loginExists($login, $existing ? (int) $existing['id'] : null)) {
            $errors[] = 'Такой логин уже занят.';
        }
        if (!$existing && $password === '') {
            $errors[] = 'Задайте пароль для нового сотрудника.';
        }
        if ($password !== '') {
            $errors = array_merge($errors, Auth::passwordErrors($password, $passwordConfirm));
        }

        if ($existing && (int) $existing['id'] === Auth::id()) {
            if ($role !== (string) $existing['role']) {
                $errors[] = 'Нельзя изменить собственную роль. Это может сделать другой администратор.';
            }
            if (!$active) {
                $errors[] = 'Нельзя отключить собственный доступ.';
            }
            $role = (string) $existing['role'];
            $active = (bool) $existing['active'];
        }

        if ($existing && $existing['role'] === 'admin' && ((string) $existing['active'] === '1') && ($role !== 'admin' || !$active)) {
            if (User::activeAdminCount() <= 1) {
                $errors[] = 'Нельзя лишить прав последнего администратора.';
            }
        }
        if ($existing && (int) $existing['id'] === Auth::id() && !$active) {
            $errors[] = 'Нельзя отключить собственный доступ.';
        }

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            redirect('/employees');
        }

        try {
            if ($existing) {
                User::update((int) $existing['id'], [
                    'login' => $login,
                    'name' => $name,
                    'role' => $role,
                    'permissions' => $permissions,
                    'active' => $active,
                    'password' => $password,
                ]);
                Audit::log('user.update', ['id' => (int) $existing['id'], 'login' => $login, 'role' => $role, 'active' => $active]);
                flash('success', 'Данные сотрудника обновлены.');
            } else {
                $newId = User::create([
                    'login' => $login,
                    'name' => $name,
                    'password' => $password,
                    'role' => $role,
                    'permissions' => $permissions,
                    'active' => $active,
                ]);
                Audit::log('user.create', ['id' => $newId, 'login' => $login, 'role' => $role]);
                flash('success', 'Сотрудник добавлен.');
            }
        } catch (\Throwable $e) {
            error_log('[PIZ] user save: ' . $e->getMessage());
            flash('error', 'Не удалось сохранить сотрудника: ' . $e->getMessage());
        }

        redirect('/employees');
    }

    public function toggle(): void
    {
        Auth::requirePermission('users.manage');
        $id = (int) ($_POST['id'] ?? 0);
        $user = User::find($id);
        if (!$user) {
            flash('error', 'Сотрудник не найден.');
            redirect('/employees');
        }
        if ($id === Auth::id()) {
            flash('error', 'Нельзя отключить собственный доступ.');
            redirect('/employees');
        }
        $active = !((int) $user['active'] === 1);
        if (!$active && $user['role'] === 'admin' && User::activeAdminCount() <= 1) {
            flash('error', 'Нельзя отключить последнего администратора.');
            redirect('/employees');
        }
        User::setActive($id, $active);
        Audit::log('user.toggle', ['id' => $id, 'login' => $user['login'], 'active' => $active]);
        flash('success', $active ? 'Доступ сотрудника включён.' : 'Доступ сотрудника отключён.');
        redirect('/employees');
    }

    public function password(): void
    {
        Auth::requirePermission('users.manage');
        $id = (int) ($_POST['id'] ?? 0);
        $user = User::find($id);
        if (!$user) {
            flash('error', 'Сотрудник не найден.');
            redirect('/employees');
        }
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirm'] ?? '');
        $errors = Auth::passwordErrors($password, $confirmation);
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            redirect('/employees');
        }
        User::setPassword($id, $password);
        Audit::log('user.password', ['id' => $id, 'login' => $user['login']]);
        flash('success', 'Пароль сотрудника «' . $user['name'] . '» изменён.');
        redirect('/employees');
    }
}
