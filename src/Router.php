<?php
declare(strict_types=1);

namespace PIZ;

final class Router
{
    /** @var array<string, array<int, array{pattern: string, regex: string, handler: callable|array}>> */
    private static array $routes = [];

    public static function get(string $pattern, callable|array $handler): void
    {
        self::add('GET', $pattern, $handler);
    }

    public static function post(string $pattern, callable|array $handler): void
    {
        self::add('POST', $pattern, $handler);
    }

    public static function add(string $method, string $pattern, callable|array $handler): void
    {
        $pattern = '/' . trim($pattern, '/');
        if ($pattern === '/') {
            $pattern = '/';
        }
        self::$routes[strtoupper($method)][] = [
            'pattern' => $pattern,
            'regex' => self::compile($pattern),
            'handler' => $handler,
        ];
    }

    private static function compile(string $pattern): string
    {
        $parts = preg_split('/(\{\w+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            if (preg_match('/^\{(\w+)\}$/', $part, $m)) {
                $regex .= '(?P<' . $m[1] . '>[^/]+)';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }
        return '#^' . $regex . '$#';
    }

    public static function dispatch(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = self::currentPath();

        $allowedMethods = [];
        foreach (self::$routes as $routeMethod => $routes) {
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $path, $matches) !== 1) {
                    continue;
                }
                $allowedMethods[] = $routeMethod;
                if ($routeMethod !== $method) {
                    continue;
                }
                if ($method === 'POST') {
                    if (str_starts_with($path, '/api/') && !Auth::check()) {
                        json_response(['ok' => false, 'error' => 'Требуется вход в систему.'], 401);
                    }
                    verify_csrf();
                }
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                $handler = $route['handler'];
                if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && class_exists($handler[0])) {
                    $controller = new $handler[0]();
                    $handler = [$controller, $handler[1]];
                }
                call_user_func_array($handler, $params);
                return;
            }
        }

        if ($allowedMethods) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            View::render('errors/404', ['title' => 'Метод не поддерживается', 'showLayout' => Auth::check()], Auth::check() ? 'layout' : 'layout-bare');
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Страница не найдена', 'showLayout' => Auth::check()], Auth::check() ? 'layout' : 'layout-bare');
    }

    public static function currentPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';

        $base = base_url();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        if ($path === '' || $path === '/index.php') {
            if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
                $path = $_GET['r'];
            } else {
                $path = '/';
            }
        }
        if (str_starts_with($path, '/index.php/')) {
            $path = substr($path, strlen('/index.php'));
        }

        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}
