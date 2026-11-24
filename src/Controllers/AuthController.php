<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        if (!\PIZ\Install::isConfigured()) {
            redirect('/setup');
        }
        if (!\PIZ\Install::hasUsers()) {
            redirect('/setup/admin');
        }

        View::render('auth/login', [
            'title' => 'Вход',
            'logout' => isset($_GET['logout']),
            'expired' => isset($_GET['expired']),
            'reset' => isset($_GET['reset']),
        ], 'layout-bare');
    }

    public function login(): void
    {
        if (Auth::check()) {
            redirect('/');
        }

        $login = (string) ($_POST['login'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $result = Auth::attempt($login, $password);

        if (!$result['ok']) {
            View::render('auth/login', [
                'title' => 'Вход',
                'error' => $result['error'] ?? 'Не удалось войти.',
                'oldLogin' => $login,
            ], 'layout-bare');
            return;
        }

        $intended = (string) ($_SESSION['intended'] ?? '');
        unset($_SESSION['intended']);
        if ($intended !== '' && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            redirect($intended);
        }
        redirect('/');
    }

    public function logout(): void
    {
        if (Auth::check()) {
            Audit::log('auth.logout');
        }
        Auth::logout();
        redirect('/login?logout=1');
    }
}
