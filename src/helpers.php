<?php
declare(strict_types=1);

use PIZ\Auth;
use PIZ\Settings;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('today')) {
    function today(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('base_url')) {
    function base_url(): string
    {
        static $base = null;
        if ($base === null) {
            if (PHP_SAPI === 'cli-server') {
                return $base = '';
            }
            $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
            $dir = str_replace('\\', '/', dirname($script));
            $base = ($dir === '/' || $dir === '.' || $dir === '') ? '' : rtrim($dir, '/');
        }
        return $base;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return base_url() . '/' . $path;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $file = PIZ_ROOT . '/' . ltrim($path, '/');
        $version = is_file($file) ? (string) filemtime($file) : PIZ_VERSION;
        return url($path) . '?v=' . $version;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): never
    {
        $target = preg_match('#^https?://#i', $path) ? $path : url($path);
        header('Location: ' . $target, true, $status);
        exit;
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flashes')) {
    function flashes(): array
    {
        $items = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $items;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): void
    {
        $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
            if (is_json_request()) {
                json_response(['ok' => false, 'error' => 'Сессия истекла. Обновите страницу и попробуйте снова.'], 419);
            }
            http_response_code(419);
            \PIZ\View::render('errors/419', ['title' => 'Сессия истекла'], 'layout-bare');
            exit;
        }
    }
}

if (!function_exists('is_json_request')) {
    function is_json_request(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json')
            || strtolower($requested) === 'xmlhttprequest'
            || str_contains($type, 'application/json');
    }
}

if (!function_exists('json_response')) {
    function json_response(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('request_post')) {
    function request_post(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($type, 'application/json')) {
            $raw = file_get_contents('php://input');
            $data = json_decode((string) $raw, true);
            return is_array($data) ? $data : [];
        }
        return $_POST;
    }
}

if (!function_exists('currency')) {
    function currency(): string
    {
        return (string) Settings::get('currency', '₽');
    }
}

if (!function_exists('money')) {
    /**
     * Форматирует сумму из копеек. По умолчанию копейки показываются только если они есть.
     */
    function money(int|float|null $kop, bool $alwaysDecimals = false): string
    {
        $kop = (int) round((float) $kop);
        $abs = abs($kop);
        $negative = $kop < 0;
        if ($alwaysDecimals || $abs % 100 !== 0) {
            $value = number_format($abs / 100, 2, ',', ' ');
        } else {
            $value = number_format($abs / 100, 0, ',', ' ');
        }
        return ($negative ? '−' : '') . $value . ' ' . currency();
    }
}

if (!function_exists('money_short')) {
    /** Компактная сумма для осей графиков: 12,5 тыс. */
    function money_short(int $kop): string
    {
        $rub = $kop / 100;
        if (abs($rub) >= 1000000) {
            return rtrim(rtrim(number_format($rub / 1000000, 1, ',', ' '), '0'), ',') . ' млн';
        }
        if (abs($rub) >= 1000) {
            return rtrim(rtrim(number_format($rub / 1000, 1, ',', ' '), '0'), ',') . ' тыс.';
        }
        return number_format($rub, 0, ',', ' ');
    }
}

if (!function_exists('parse_money')) {
    /**
     * Преобразует введённую пользователем сумму ("1 234,50", "234.5") в копейки.
     * Возвращает null, если значение не является корректной суммой.
     */
    function parse_money(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (int) round($value * 100);
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = str_replace(["\xC2\xA0", "\xE2\x80\xAF", ' '], '', $value);
        $value = str_replace(',', '.', $value);
        if (!preg_match('/^-?\d+(\.\d{1,4})?$/', $value)) {
            return null;
        }
        return (int) round((float) $value * 100);
    }
}

if (!function_exists('kop_to_input')) {
    function kop_to_input(?int $kop): string
    {
        $kop = (int) $kop;
        return rtrim(rtrim(number_format($kop / 100, 2, '.', ''), '0'), '.');
    }
}

if (!function_exists('qty_format')) {
    function qty_format(int|float $qty): string
    {
        return (string) (int) $qty;
    }
}

if (!function_exists('dt_human')) {
    function dt_human(?string $datetime): string
    {
        if (!$datetime) {
            return '—';
        }
        $ts = strtotime($datetime);
        return $ts ? date('d.m.Y H:i', $ts) : '—';
    }
}

if (!function_exists('time_human')) {
    function time_human(?string $datetime): string
    {
        if (!$datetime) {
            return '—';
        }
        $ts = strtotime($datetime);
        return $ts ? date('H:i', $ts) : '—';
    }
}

if (!function_exists('date_human')) {
    function date_human(?string $date): string
    {
        if (!$date) {
            return '—';
        }
        $ts = strtotime($date);
        return $ts ? date('d.m.Y', $ts) : '—';
    }
}

if (!function_exists('plural')) {
    function plural(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return $many;
        }
        if ($n1 > 1 && $n1 < 5) {
            return $few;
        }
        if ($n1 === 1) {
            return $one;
        }
        return $many;
    }
}

if (!function_exists('percent_change')) {
    /** Процент изменения текущего значения к предыдущему. null, если сравнить нельзя. */
    function percent_change(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : null;
        }
        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}

if (!function_exists('greeting')) {
    function greeting(): string
    {
        $hour = (int) date('G');
        if ($hour < 5) {
            return 'Доброй ночи';
        }
        if ($hour < 12) {
            return 'Доброе утро';
        }
        if ($hour < 18) {
            return 'Добрый день';
        }
        return 'Добрый вечер';
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $parts = array_filter($parts);
        if (!$parts) {
            return '?';
        }
        $first = mb_substr((string) reset($parts), 0, 1);
        $second = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
        return mb_strtoupper($first . $second);
    }
}

if (!function_exists('active_class')) {
    function active_class(string $current, string $expected): string
    {
        return $current === $expected ? 'is-active' : '';
    }
}

if (!function_exists('icon')) {
    function icon(string $name, string $class = 'icon'): string
    {
        return '<svg class="' . e($class) . '" aria-hidden="true" focusable="false"><use href="#i-' . e($name) . '"></use></svg>';
    }
}

if (!function_exists('payment_label')) {
    function payment_label(string $method): string
    {
        return [
            'cash' => 'Наличные',
            'card' => 'Карта',
            'transfer' => 'Перевод',
        ][$method] ?? $method;
    }
}

if (!function_exists('payment_icon')) {
    function payment_icon(string $method): string
    {
        return [
            'cash' => 'cash',
            'card' => 'card',
            'transfer' => 'bank',
        ][$method] ?? 'wallet';
    }
}

if (!function_exists('role_label')) {
    function role_label(string $role): string
    {
        return [
            'admin' => 'Администратор',
            'manager' => 'Управляющий',
            'cashier' => 'Кассир',
            'custom' => 'Свои права',
        ][$role] ?? $role;
    }
}

if (!function_exists('audit')) {
    function audit(string $action, mixed $details = null): void
    {
        try {
            \PIZ\Audit::log($action, $details);
        } catch (\Throwable $e) {
            // журнал не должен ломать основной сценарий
        }
    }
}

if (!function_exists('query_url')) {
    /** Ссылка с сохранением текущих GET-параметров (для фильтров и пагинации). */
    function query_url(string $path, array $overrides = []): string
    {
        $query = array_merge($_GET, $overrides);
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            }
        }
        return url($path) . ($query !== [] ? '?' . http_build_query($query) : '');
    }
}

if (!function_exists('pdf_url')) {
    /**
     * Ссылка на PDF с версией, чтобы браузер не отдавал старый файл из кеша.
     */
    function pdf_url(string $path, array $params = []): string
    {
        $params['v'] = PIZ_VERSION;
        return url($path) . '?' . http_build_query($params);
    }
}

if (!function_exists('period_range')) {
    /**
     * Разбирает выбранный период для истории продаж и отчётов.
     *
     * @return array{period: string, from: string, to: string, start: string, end: string}
     */
    function period_range(): array
    {
        $period = (string) ($_GET['period'] ?? '30d');
        $today = today();
        $from = $today;
        $to = $today;

        switch ($period) {
            case 'today':
                break;
            case '7d':
                $from = date('Y-m-d', strtotime($today . ' -6 days'));
                break;
            case '30d':
                $from = date('Y-m-d', strtotime($today . ' -29 days'));
                break;
            case 'month':
                $from = date('Y-m-01');
                break;
            case 'custom':
                $from = \PIZ\Models\Sale::validDate((string) ($_GET['from'] ?? '')) ?? $today;
                $to = \PIZ\Models\Sale::validDate((string) ($_GET['to'] ?? '')) ?? $today;
                break;
            case 'all':
                $from = '2000-01-01';
                break;
            default:
                $period = '30d';
                $from = date('Y-m-d', strtotime($today . ' -29 days'));
        }

        if ($period === 'all') {
            try {
                $earliest = \PIZ\Database::fetchValue('SELECT MIN(created_at) FROM sales');
            } catch (\Throwable) {
                $earliest = null;
            }
            $from = $earliest ? date('Y-m-d', strtotime((string) $earliest)) : $today;
            $to = $today;
        }

        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'start' => $from . ' 00:00:00',
            'end' => date('Y-m-d 00:00:00', strtotime($to . ' +1 day')),
        ];
    }
}

if (!function_exists('audit_label')) {
    function audit_label(string $action): string
    {
        $map = [
            'auth.login' => 'Вход в систему',
            'auth.logout' => 'Выход',
            'product.create' => 'Товар создан',
            'product.update' => 'Товар изменён',
            'product.archive' => 'Товар в архиве',
            'product.restore' => 'Товар возвращён',
            'stock.in' => 'Приход товара',
            'stock.adjust' => 'Инвентаризация',
            'product.import' => 'Импорт товаров',
            'sale.create' => 'Продажа',
            'sale.cancel' => 'Отмена продажи',
            'user.create' => 'Сотрудник создан',
            'user.update' => 'Сотрудник изменён',
            'user.password' => 'Пароль сотрудника изменён',
            'user.toggle' => 'Доступ сотрудника изменён',
            'settings.update' => 'Настройки изменены',
            'password.change' => 'Свой пароль изменён',
            'install.database' => 'Установка: база данных',
            'install.admin' => 'Установка: администратор',
        ];
        return $map[$action] ?? $action;
    }
}

if (!function_exists('csv_safe')) {
    /** Защищает CSV от формул Excel (CSV injection). */
    function csv_safe(mixed $value): string
    {
        $string = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $string) === 1 ? "'" . $string : $string;
    }
}

if (!function_exists('product_photo')) {
    /** Миниатюра товара: фото или буквенный плейсхолдер с детерминированным оттенком. */
    function product_photo(array $product, string $class = 'thumb'): string
    {
        $url = \PIZ\Services\ImageUploader::url($product['photo'] ?? null);
        $name = (string) ($product['name'] ?? '');
        if ($url !== null) {
            return '<img class="' . e($class) . '" src="' . e($url) . '" alt="' . e($name) . '" loading="eager" decoding="async">';
        }
        $hue = crc32($name) % 360;
        $letter = mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1));
        return '<span class="' . e($class) . ' thumb-ph" style="--hue: ' . $hue . '"><span>' . e($letter) . '</span></span>';
    }
}

if (!function_exists('payment_badge')) {
    function payment_badge(string $method): string
    {
        return '<span class="badge badge--pay badge--' . e($method) . '">' . icon(payment_icon($method), 'icon icon--xs') . e(payment_label($method)) . '</span>';
    }
}

if (!function_exists('sale_status_badge')) {
    function sale_status_badge(string $status): string
    {
        return $status === 'canceled'
            ? '<span class="badge badge--red">Отменена</span>'
            : '<span class="badge badge--green">Завершена</span>';
    }
}

if (!function_exists('trend_badge')) {
    function trend_badge(?float $percent): string
    {
        if ($percent === null) {
            return '<span class="trend trend--flat">—</span>';
        }
        $up = $percent >= 0;
        $value = ($up ? '+' : '−') . number_format(abs($percent), 0, ',', ' ') . '%';
        return '<span class="trend ' . ($up ? 'trend--up' : 'trend--down') . '">'
            . icon($up ? 'arrow-up' : 'arrow-down', 'icon icon--xs') . $value . '</span>';
    }
}

if (!function_exists('period_label')) {
    function period_label(string $from, string $to): string
    {
        if ($from === $to) {
            return date('d.m.Y', strtotime($from));
        }
        return date('d.m.Y', strtotime($from)) . ' — ' . date('d.m.Y', strtotime($to));
    }
}

if (!function_exists('movement_label')) {
    function movement_label(string $type): string
    {
        return [
            'in' => 'Приход',
            'sale' => 'Продажа',
            'cancel' => 'Возврат',
            'adjust' => 'Инвентаризация',
            'initial' => 'Начальный остаток',
            'writeoff' => 'Списание',
            'inventory' => 'Инвентаризация',
        ][$type] ?? $type;
    }
}

if (!function_exists('movement_tone')) {
    function movement_tone(string $type): string
    {
        return [
            'in' => 'green',
            'sale' => 'blue',
            'cancel' => 'amber',
            'adjust' => 'gray',
            'initial' => 'gray',
            'writeoff' => 'red',
            'inventory' => 'amber',
        ][$type] ?? 'gray';
    }
}

if (!function_exists('price_source_label')) {
    function price_source_label(string $source): string
    {
        return [
            'create' => 'Создание',
            'manual' => 'Вручную',
            'bulk' => 'Массово',
            'receipt' => 'Приход',
            'import' => 'Импорт',
            'baseline' => 'Начальная',
        ][$source] ?? $source;
    }
}

if (!function_exists('invoice_status_badge')) {
    function invoice_status_badge(string $status): string
    {
        return match ($status) {
            'paid' => '<span class="badge badge--green">Оплачена</span>',
            'partial' => '<span class="badge badge--amber">Частично</span>',
            'canceled' => '<span class="badge badge--red">Отменена</span>',
            default => '<span class="badge badge--gray">Не оплачена</span>',
        };
    }
}

if (!function_exists('absolute_url')) {
    /**
     * Абсолютный URL для писем и внешних ссылок.
     */
    function absolute_url(string $path = ''): string
    {
        $configured = \PIZ\Settings::get('site_url');
        if ($configured !== null && $configured !== '') {
            return rtrim($configured, '/') . url($path);
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'piz.itop7.ru');
        if (!preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host)) {
            $host = 'piz.itop7.ru';
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || !in_array($host, ['localhost', '127.0.0.1', 'localhost:8000'], true);
        return ($https ? 'https' : 'http') . '://' . $host . url($path);
    }
}
