<?php
declare(strict_types=1);

namespace PIZ;

use PIZ\Models\User;
use PIZ\Services\Importer;

final class Install
{
    public static function configFile(): string
    {
        return PIZ_ROOT . '/config/config.php';
    }

    public static function isConfigured(): bool
    {
        return is_file(self::configFile()) && Database::isConnected();
    }

    public static function hasUsers(): bool
    {
        if (!self::isConfigured()) {
            return false;
        }
        try {
            return User::count() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<int, array{label: string, ok: bool, hint: string}> */
    public static function requirements(): array
    {
        $checks = [];
        $checks[] = [
            'label' => 'PHP ' . PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'hint' => 'Нужен PHP 8.1 или новее.',
        ];
        foreach ([
            'pdo_mysql' => 'Подключение к MySQL',
            'mbstring' => 'Работа с русским текстом',
            'json' => 'JSON',
            'fileinfo' => 'Проверка загружаемых файлов',
        ] as $extension => $label) {
            $checks[] = [
                'label' => $label . ' (' . $extension . ')',
                'ok' => extension_loaded($extension),
                'hint' => 'Включите расширение ' . $extension . ' в настройках PHP.',
            ];
        }
        $checks[] = [
            'label' => 'Обработка фото (gd)',
            'ok' => extension_loaded('gd'),
            'hint' => 'Без GD фото будет сохраняться без сжатия — рекомендуется включить.',
            'optional' => true,
        ];
        $checks[] = [
            'label' => 'Импорт Excel (zip)',
            'ok' => class_exists(\ZipArchive::class),
            'hint' => 'Без zip-расширения используйте импорт CSV.',
            'optional' => true,
        ];
        foreach ([
            'config' => 'config',
            'uploads' => 'uploads',
            'storage/logs' => 'storage/logs',
        ] as $dir => $label) {
            $path = PIZ_ROOT . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $checks[] = [
                'label' => 'Запись в ' . $label,
                'ok' => is_dir($path) && is_writable($path),
                'hint' => 'Выдайте права 755 (или 775) на папку ' . $label . '.',
            ];
        }
        return $checks;
    }

    public static function requirementsOk(): bool
    {
        foreach (self::requirements() as $check) {
            if (empty($check['ok']) && empty($check['optional'])) {
                return false;
            }
        }
        return true;
    }

    public static function configContent(array $db): string
    {
        $data = [
            'db' => [
                'host' => $db['host'],
                'port' => $db['port'] ?? '3306',
                'name' => $db['name'],
                'user' => $db['user'],
                'pass' => $db['pass'],
                'charset' => 'utf8mb4',
            ],
            'debug' => false,
        ];

        return "<?php\n"
            . "// Конфигурация создана установщиком " . date('d.m.Y H:i') . "\n"
            . "// Не публикуйте этот файл и не коммитьте его в Git.\n"
            . "return " . var_export($data, true) . ";\n";
    }

    public static function writeConfig(array $db): void
    {
        $dir = PIZ_ROOT . '/config';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new \RuntimeException('Не удалось создать папку config.');
        }
        if (!is_writable($dir) && !is_file(self::configFile())) {
            throw new \RuntimeException('Папка config недоступна для записи. Создайте файл config/config.php вручную.');
        }

        $content = self::configContent($db);

        $tmp = self::configFile() . '.tmp';
        if (@file_put_contents($tmp, $content) === false) {
            throw new \RuntimeException('Не удалось записать config/config.php. Проверьте права на папку config.');
        }
        @chmod($tmp, 0640);
        if (!@rename($tmp, self::configFile())) {
            @unlink($tmp);
            throw new \RuntimeException('Не удалось сохранить config/config.php.');
        }
        @chmod(self::configFile(), 0640);
    }

    /**
     * Выполняет database/schema.sql (можно запускать повторно — используются IF NOT EXISTS).
     */
    public static function migrate(): void
    {
        $file = PIZ_ROOT . '/database/schema.sql';
        if (!is_file($file)) {
            throw new \RuntimeException('Не найден файл database/schema.sql.');
        }
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
        $pdo = Database::pdo();
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function seedCatalog(): ?array
    {
        return Importer::seedCatalog();
    }
}
