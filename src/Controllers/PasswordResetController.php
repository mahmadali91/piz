<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Models\PasswordReset;
use PIZ\Models\User;
use PIZ\Services\Mailer;
use PIZ\View;

final class PasswordResetController
{
    public function showRequest(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        View::render('auth/forgot', [
            'title' => 'Восстановление пароля',
            'sent' => isset($_GET['sent']),
        ], 'layout-bare');
    }

    public function send(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        $identity = trim((string) ($_POST['identity'] ?? ''));
        $last = (int) ($_SESSION['password_reset_sent_at'] ?? 0);
        if (time() - $last < 60) {
            flash('error', 'Письмо уже запрошено. Подождите минуту перед повторной отправкой.');
            redirect('/forgot-password');
        }

        $user = User::findByLogin($identity);
        if (!$user) {
            $user = User::findByEmail($identity);
        }
        if (!$user) {
            flash('error', 'Аккаунт с таким логином или email не найден.');
            redirect('/forgot-password');
        }
        if (empty($user['email'])) {
            flash('error', 'У этого аккаунта не указан email. Попросите администратора добавить его в карточке сотрудника.');
            redirect('/forgot-password');
        }

        try {
            $token = PasswordReset::issue((int) $user['id'], Auth::ip());
            $link = absolute_url('/reset-password?token=' . urlencode($token));
            if (!Mailer::sendPasswordReset($user, $link)) {
                throw new \RuntimeException('Почтовый сервер не принял письмо.');
            }
            Audit::log('auth.password_reset_request', ['user_id' => (int) $user['id']]);
        } catch (\Throwable $e) {
            error_log('[PIZ] password reset: ' . $e->getMessage());
            flash('error', 'Не удалось отправить письмо. Проверьте настройки почты или попробуйте позже.');
            redirect('/forgot-password');
        }
        $_SESSION['password_reset_sent_at'] = time();
        flash('success', 'Письмо отправлено. Проверьте почту и папку «Спам».');
        redirect('/forgot-password?sent=1');
    }

    public function showReset(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        $token = (string) ($_GET['token'] ?? '');
        $reset = PasswordReset::findValid($token);
        View::render('auth/reset', [
            'title' => 'Новый пароль',
            'token' => $token,
            'valid' => $reset !== null,
        ], 'layout-bare');
    }

    public function reset(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        $token = (string) ($_POST['token'] ?? '');
        $reset = PasswordReset::findValid($token);
        if (!$reset) {
            View::render('auth/reset', [
                'title' => 'Новый пароль',
                'token' => $token,
                'valid' => false,
            ], 'layout-bare');
            return;
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirm'] ?? '');
        $errors = Auth::passwordErrors($password, $confirmation);
        if ($errors !== []) {
            View::render('auth/reset', [
                'title' => 'Новый пароль',
                'token' => $token,
                'valid' => true,
                'error' => implode(' ', $errors),
            ], 'layout-bare');
            return;
        }

        User::setPassword((int) $reset['user_id'], $password);
        PasswordReset::consume((int) $reset['id']);
        Audit::log('auth.password_reset', ['user_id' => (int) $reset['user_id']]);
        flash('success', 'Пароль изменён. Войдите с новым паролем.');
        redirect('/login?reset=1');
    }
}
