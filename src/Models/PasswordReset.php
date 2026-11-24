<?php
declare(strict_types=1);

namespace PIZ\Models;

use PIZ\Database;

final class PasswordReset
{
    public static function issue(int $userId, string $ip): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        Database::query('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $userId]);
        Database::query(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at, ip) VALUES (?, ?, ?, ?, ?)',
            [$userId, $hash, date('Y-m-d H:i:s', time() + 3600), now(), mb_substr($ip, 0, 45)]
        );
        Database::query('DELETE FROM password_resets WHERE expires_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        return $token;
    }

    public static function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = Database::fetch(
            'SELECT pr.*, u.name, u.login, u.email
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > ? AND u.active = 1
             LIMIT 1',
            [hash('sha256', $token), now()]
        );
        return $row ?: null;
    }

    public static function consume(int $id): void
    {
        Database::query('UPDATE password_resets SET used_at = ? WHERE id = ?', [now(), $id]);
    }
}
