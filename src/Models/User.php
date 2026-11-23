<?php
declare(strict_types=1);

namespace PIZ\Models;

use PIZ\Database;
use PIZ\Permissions;

final class User
{
    public static function find(int $id): ?array
    {
        $row = Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
        return $row ? self::prepare($row) : null;
    }

    public static function findByLogin(string $login): ?array
    {
        $row = Database::fetch('SELECT * FROM users WHERE login = ?', [$login]);
        return $row ? self::prepare($row) : null;
    }

    public static function all(): array
    {
        $rows = Database::fetchAll('SELECT * FROM users ORDER BY active DESC, name ASC');
        return array_map([self::class, 'prepare'], $rows);
    }

    public static function prepare(array $row): array
    {
        $permissions = json_decode((string) ($row['permissions'] ?? '[]'), true);
        $row['permissions'] = is_array($permissions) ? Permissions::sanitize($permissions) : [];
        return $row;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO users (login, name, password_hash, role, permissions, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['login'],
                $data['name'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['role'],
                json_encode(array_values($data['permissions']), JSON_UNESCAPED_UNICODE),
                $data['active'] ? 1 : 0,
                now(),
            ]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE users SET login = ?, name = ?, role = ?, permissions = ?, active = ?';
        $params = [
            $data['login'],
            $data['name'],
            $data['role'],
            json_encode(array_values($data['permissions']), JSON_UNESCAPED_UNICODE),
            $data['active'] ? 1 : 0,
        ];
        if (!empty($data['password'])) {
            $sql .= ', password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        Database::query($sql, $params);
    }

    public static function setPassword(int $id, string $password): void
    {
        Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::query('UPDATE users SET active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    public static function activeAdminCount(): int
    {
        return (int) Database::fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
    }

    public static function loginExists(string $login, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE login = ?';
        $params = [$login];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        return (int) Database::fetchValue($sql, $params) > 0;
    }

    public static function count(): int
    {
        return (int) Database::fetchValue('SELECT COUNT(*) FROM users');
    }
}
