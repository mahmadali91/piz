<?php
declare(strict_types=1);

/**
 * Служебные команды для прямого обслуживания проекта по SSH.
 *
 * Запуск только из командной строки:
 *   php tools/maintenance.php stats
 *   php tools/maintenance.php stock:all 100
 *   php tools/maintenance.php photos:generate --all
 *   php tools/maintenance.php user:temp qa StrongPass123
 *   php tools/maintenance.php user:delete qa
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';

use PIZ\Database;

$command = $argv[1] ?? 'help';

if (!Database::isConnected()) {
    fwrite(STDERR, "Нет подключения к базе. Проверьте config/config.php\n");
    exit(1);
}

$adminId = (int) (Database::fetchValue("SELECT id FROM users WHERE role = 'admin' AND active = 1 ORDER BY id LIMIT 1") ?? 0);

switch ($command) {
    case 'stats':
        stats();
        break;

    case 'stock:all':
        $qty = isset($argv[2]) ? (int) $argv[2] : -1;
        if ($qty < 0 || $qty > 1000000) {
            fail('Укажите количество: php tools/maintenance.php stock:all 100');
        }
        stockAll($qty, $adminId);
        break;

    case 'photos:generate':
        $force = in_array('--all', $argv, true) || in_array('--force', $argv, true);
        generatePhotos($force);
        break;

    case 'user:temp':
        $login = strtolower(trim((string) ($argv[2] ?? '')));
        $password = (string) ($argv[3] ?? '');
        if (!preg_match('/^[a-z0-9._-]{3,32}$/', $login) || mb_strlen($password) < 8) {
            fail('Использование: php tools/maintenance.php user:temp <login> <пароль от 8 символов>');
        }
        temporaryUser($login, $password);
        break;

    case 'user:delete':
        $login = strtolower(trim((string) ($argv[2] ?? '')));
        if ($login === '') {
            fail('Использование: php tools/maintenance.php user:delete <login>');
        }
        deleteUser($login);
        break;

    default:
        echo <<<TXT
PIZ maintenance

  php tools/maintenance.php stats
  php tools/maintenance.php stock:all 100
  php tools/maintenance.php photos:generate --all
  php tools/maintenance.php user:temp qa StrongPass123
  php tools/maintenance.php user:delete qa

TXT;
        exit(0);
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function stats(): void
{
    $products = (int) Database::fetchValue('SELECT COUNT(*) FROM products');
    $active = (int) Database::fetchValue('SELECT COUNT(*) FROM products WHERE active = 1');
    $withPhoto = (int) Database::fetchValue("SELECT COUNT(*) FROM products WHERE photo IS NOT NULL AND photo <> ''");
    $sales = (int) Database::fetchValue('SELECT COUNT(*) FROM sales');
    $users = (int) Database::fetchValue('SELECT COUNT(*) FROM users');
    $units = (int) Database::fetchValue('SELECT COALESCE(SUM(stock_qty), 0) FROM products WHERE active = 1');

    echo "Товаров: {$products} (активных {$active}, с фото {$withPhoto})\n";
    echo "Остаток единиц: {$units}\n";
    echo "Продаж: {$sales}\n";
    echo "Пользователей: {$users}\n";
}

function stockAll(int $qty, int $adminId): void
{
    $pdo = Database::pdo();
    $pdo->beginTransaction();
    try {
        $products = Database::fetchAll(
            'SELECT id, name, stock_qty, purchase_price_kop, unit FROM products WHERE active = 1 FOR UPDATE'
        );
        $changed = 0;
        foreach ($products as $product) {
            $stock = (int) $product['stock_qty'];
            $delta = $qty - $stock;
            if ($delta === 0) {
                continue;
            }
            Database::query(
                'UPDATE products SET stock_qty = ?, updated_at = ? WHERE id = ?',
                [$qty, now(), (int) $product['id']]
            );
            Database::query(
                'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                 VALUES (?, "adjust", ?, ?, ?, ?, ?, ?)',
                [
                    (int) $product['id'],
                    $delta,
                    $qty,
                    (int) $product['purchase_price_kop'],
                    'Массовая установка остатка: ' . $qty . ' ' . $product['unit'],
                    $adminId ?: null,
                    now(),
                ]
            );
            $changed++;
        }
        $pdo->commit();
        echo "Обновлено товаров: {$changed}. Остаток установлен: {$qty}.\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        fail('Ошибка: ' . $e->getMessage());
    }
}

/**
 * Рисует квадратные миниатюры с названием товара и сохраняет их в uploads/products.
 */
function generatePhotos(bool $force): void
{
    if (!function_exists('imagecreatetruecolor')) {
        fail('На сервере не включено расширение GD — генерация изображений невозможна.');
    }

    $dir = PIZ_ROOT . '/uploads/products';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail('Не удалось создать ' . $dir);
    }

    $font = findFont();
    if ($font === null) {
        fail('Не найден TTF-шрифт с кириллицей. Установите fonts-dejavu-core или загрузите фото вручную.');
    }

    $products = Database::fetchAll(
        'SELECT id, name, sku, category, sale_price_kop, photo FROM products WHERE active = 1 ORDER BY id'
    );
    if ($products === []) {
        fail('Нет активных товаров.');
    }

    $done = 0;
    $skipped = 0;
    foreach ($products as $product) {
        if (!$force && !empty($product['photo']) && is_file(PIZ_ROOT . '/' . $product['photo'])) {
            $skipped++;
            continue;
        }

        $name = trim((string) $product['name']);
        $id = (int) $product['id'];
        $width = 600;
        $height = 600;
        $image = imagecreatetruecolor($width, $height);

        $hue = (crc32($name) % 360) / 360;
        [$r1, $g1, $b1] = hslToRgb($hue, 0.62, 0.93);
        [$r2, $g2, $b2] = hslToRgb($hue, 0.55, 0.78);

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $color = imagecolorallocate(
                $image,
                (int) round($r1 + ($r2 - $r1) * $t),
                (int) round($g1 + ($g2 - $g1) * $t),
                (int) round($b1 + ($b2 - $b1) * $t)
            );
            imageline($image, 0, $y, $width, $y, $color);
        }

        // декоративные круги
        $soft = imagecolorallocatealpha($image, 255, 255, 255, 96);
        imagefilledellipse($image, 520, 90, 260, 260, $soft);
        imagefilledellipse($image, 70, 500, 200, 200, $soft);

        // буква-водяной знак
        $letter = mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1));
        $letterColor = imagecolorallocatealpha($image, 255, 255, 255, 28);
        $box = imagettfbbox(250, 0, $font, $letter);
        $letterWidth = abs($box[2] - $box[0]);
        imagettftext($image, 250, 0, (int) (($width - $letterWidth) / 2), 420, $letterColor, $font, $letter);

        // плашка для названия
        $white = imagecolorallocatealpha($image, 255, 255, 255, 18);
        imagefilledrectangle($image, 0, 420, $width, $height, $white);

        // PIZ
        $ink = imagecolorallocate($image, 40, 45, 55);
        imagettftext($image, 22, 0, 32, 52, $ink, $font, 'PIZ');
        imagettftext($image, 15, 0, 32, 78, $ink, $font, 'киоск');

        // категория
        $category = trim((string) $product['category']);
        if ($category !== '') {
            $categoryBox = imagettfbbox(17, 0, $font, $category);
            $categoryWidth = abs($categoryBox[2] - $categoryBox[0]);
            imagettftext($image, 17, 0, $width - 32 - $categoryWidth, 52, $ink, $font, $category);
        }

        // название
        $nameSize = mb_strlen($name) > 28 ? 27 : 32;
        $lines = wrapText($name, $font, $nameSize, 536);
        $lines = array_slice($lines, 0, 3);
        $y = 470;
        foreach ($lines as $line) {
            imagettftext($image, $nameSize, 0, 32, $y, $ink, $font, $line);
            $y += $nameSize + 12;
        }

        // цена
        $price = kop_to_input((int) $product['sale_price_kop']) . ' руб.';
        $priceBox = imagettfbbox(26, 0, $font, $price);
        $priceWidth = abs($priceBox[2] - $priceBox[0]);
        imagettftext($image, 26, 0, $width - 32 - $priceWidth, 570, $ink, $font, $price);

        $fileName = 'generated-' . $id . '.jpg';
        $relative = 'uploads/products/' . $fileName;
        $absolute = PIZ_ROOT . '/' . $relative;
        $saved = imagejpeg($image, $absolute, 88);
        imagedestroy($image);

        if (!$saved) {
            fwrite(STDERR, "Не удалось сохранить {$relative}\n");
            continue;
        }

        Database::query('UPDATE products SET photo = ?, updated_at = ? WHERE id = ?', [$relative, now(), $id]);
        echo "Фото: {$relative} — {$name}\n";
        $done++;
    }

    echo "Готово: {$done}, пропущено (уже есть фото): {$skipped}.\n";
}

function findFont(): ?string
{
    $candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/ttf-dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/ttf-dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
        '/usr/share/fonts/truetype/msttcorefonts/Arial_Bold.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
    ];
    foreach ($candidates as $font) {
        if (is_file($font) && is_readable($font)) {
            return $font;
        }
    }
    return null;
}

/** @return string[] */
function wrapText(string $text, string $font, int $size, int $maxWidth): array
{
    $words = preg_split('/\s+/u', $text) ?: [$text];
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        $box = imagettfbbox($size, 0, $font, $candidate);
        $width = abs($box[2] - $box[0]);
        if ($width > $maxWidth && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    return $lines;
}

/** @return array{0: int, 1: int, 2: int} */
function hslToRgb(float $h, float $s, float $l): array
{
    if ($s <= 0) {
        $v = (int) round($l * 255);
        return [$v, $v, $v];
    }
    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;
    $hue = function (float $t) use ($p, $q): float {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    };
    return [
        (int) round($hue($h + 1 / 3) * 255),
        (int) round($hue($h) * 255),
        (int) round($hue($h - 1 / 3) * 255),
    ];
}

function temporaryUser(string $login, string $password): void
{
    $id = (int) (Database::fetchValue('SELECT id FROM users WHERE login = ?', [$login]) ?? 0);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($id > 0) {
        Database::query(
            'UPDATE users SET password_hash = ?, role = "admin", active = 1, permissions = ? WHERE id = ?',
            [$hash, json_encode(\PIZ\Permissions::all(), JSON_UNESCAPED_UNICODE), $id]
        );
        echo "Пользователь {$login} обновлён (администратор).\n";
        return;
    }
    Database::query(
        'INSERT INTO users (login, name, password_hash, role, permissions, active, created_at) VALUES (?, ?, ?, "admin", ?, 1, ?)',
        [
            $login,
            'Временный тест',
            $hash,
            json_encode(\PIZ\Permissions::all(), JSON_UNESCAPED_UNICODE),
            now(),
        ]
    );
    echo "Создан временный администратор {$login}.\n";
}

function deleteUser(string $login): void
{
    $id = (int) (Database::fetchValue('SELECT id FROM users WHERE login = ?', [$login]) ?? 0);
    if ($id <= 0) {
        fail('Пользователь не найден.');
    }
    $admins = (int) Database::fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
    $isAdmin = Database::fetchValue("SELECT role FROM users WHERE id = ?", [$id]) === 'admin';
    if ($isAdmin && $admins <= 1) {
        fail('Нельзя удалить последнего администратора.');
    }
    Database::query('DELETE FROM users WHERE id = ?', [$id]);
    echo "Пользователь {$login} удалён.\n";
}
