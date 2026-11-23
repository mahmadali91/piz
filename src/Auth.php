<?php
declare(strict_types=1);

namespace PIZ;

final class Auth
{
    private const IDLE_TIMEOUT = 43200; // 12 часов
    private const MAX_ATTEMPTS_PER_LOGIN = 5;
    private const MAX_ATTEMPTS_PER_IP = 12;
    private const ATTEMPT_WINDOW_MINUTES = 15;

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        if (!Database::isConnected()) {
            return null;
        }

        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
        if ($lastActivity > 0 && time() - $lastActivity > self::IDLE_TIMEOUT) {
            self::logout();
            return null;
        }

        try {
            $row = Database::fetch('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);
        } catch (\Throwable) {
            return null;
        }

        if (!$row) {
            unset($_SESSION['user_id']);
            return null;
        }

        $row['permissions'] = self::decodePermissions($row);
        self::$user = $row;
        $_SESSION['last_activity'] = time();
        return self::$user;
    }

    /** @return string[] */
    private static function decodePermissions(array $row): array
    {
        $decoded = json_decode((string) ($row['permissions'] ?? '[]'), true);
        if (!is_array($decoded) || $decoded === []) {
            return Permissions::forRole((string) $row['role']);
        }
        return Permissions::sanitize($decoded);
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        return in_array($permission, $user['permissions'] ?? [], true);
    }

    /** @param string[] $permissions */
    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }
        return false;
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }
        if (is_json_request()) {
            json_response(['ok' => false, 'error' => 'Требуется вход в систему.'], 401);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? url('/');
        }
        redirect('/login');
    }

    public static function requireGuest(): void
    {
        if (self::check()) {
            redirect('/');
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();
        if (self::can($permission)) {
            return;
        }
        if (is_json_request()) {
            json_response(['ok' => false, 'error' => 'Недостаточно прав для этого действия.'], 403);
        }
        http_response_code(403);
        View::render('errors/403', ['title' => 'Нет доступа'], Auth::check() ? 'layout' : 'layout-bare');
        exit;
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function attempt(string $login, string $password): array
    {
        $login = self::normalizeLogin($login);
        if ($login === '' || $password === '') {
            return ['ok' => false, 'error' => 'Введите логин и пароль.'];
        }

        $ip = self::ip();
        try {
            if (self::tooManyAttempts($login, $ip)) {
                return ['ok' => false, 'error' => 'Слишком много попыток входа. Попробуйте через 15 минут.'];
            }

            $user = Database::fetch('SELECT * FROM users WHERE login = ? LIMIT 1', [$login]);
            if (!$user || (int) $user['active'] !== 1 || !password_verify($password, (string) $user['password_hash'])) {
                self::recordFailure($login, $ip);
                return ['ok' => false, 'error' => 'Неверный логин или пароль.'];
            }

            Database::query('DELETE FROM login_attempts WHERE login = ? OR ip = ?', [$login, $ip]);
            Database::query('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $user['id']]);
            self::loginUser($user);
            Audit::log('auth.login', ['login' => $login]);
            return ['ok' => true];
        } catch (\Throwable $e) {
            error_log('[PIZ] login failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Не удалось выполнить вход. Попробуйте позже.'];
        }
    }

    public static function loginUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        $_SESSION['login_at'] = time();
        unset($_SESSION['csrf_token']);
        self::$user = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$loaded = true;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function normalizeLogin(string $login): string
    {
        return mb_strtolower(trim($login));
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    private static function tooManyAttempts(string $login, string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::ATTEMPT_WINDOW_MINUTES * 60);
        $byLogin = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM login_attempts WHERE login = ? AND created_at > ?',
            [$login, $since]
        );
        if ($byLogin >= self::MAX_ATTEMPTS_PER_LOGIN) {
            return true;
        }
        $byIp = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?',
            [$ip, $since]
        );
        return $byIp >= self::MAX_ATTEMPTS_PER_IP;
    }

    private static function recordFailure(string $login, string $ip): void
    {
        Database::query('INSERT INTO login_attempts (ip, login, created_at) VALUES (?, ?, ?)', [$ip, $login, now()]);
        if (random_int(1, 20) === 1) {
            Database::query('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        }
    }

    public static function passwordErrors(string $password, string $confirmation): array
    {
        $errors = [];
        if (mb_strlen($password) < 8) {
            $errors[] = 'Пароль должен быть не короче 8 символов.';
        }
        if (preg_match('/[A-Za-zА-Яа-я]/u', $password) !== 1) {
            $errors[] = 'Пароль должен содержать хотя бы одну букву.';
        }
        if ($password !== $confirmation) {
            $errors[] = 'Пароли не совпадают.';
        }
        return $errors;
    }
}
