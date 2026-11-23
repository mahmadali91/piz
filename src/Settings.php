<?php
declare(strict_types=1);

namespace PIZ;

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            if (Database::isConnected()) {
                try {
                    foreach (Database::fetchAll('SELECT skey, svalue FROM settings') as $row) {
                        self::$cache[$row['skey']] = $row['svalue'];
                    }
                } catch (\Throwable) {
                    self::$cache = [];
                }
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();
        return array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '' ? (string) $all[$key] : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::query(
            'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
            [$key, $value]
        );
        self::$cache[$key] = (string) $value;
    }

    public static function reload(): void
    {
        self::$cache = null;
    }
}
