<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Auth;
use PIZ\View;

final class KnowledgeController
{
    public function index(): void
    {
        Auth::requireLogin();

        View::render('knowledge', [
            'title' => 'База знаний',
            'subtitle' => 'Инструкции по всем разделам сервиса',
            'active' => 'knowledge',
            'user' => Auth::user(),
        ]);
    }

    public function image(string $name): void
    {
        Auth::requireLogin();

        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
            http_response_code(404);
            return;
        }
        $file = PIZ_ROOT . '/storage/knowledge/' . $name . '.php';
        if (!is_file($file)) {
            http_response_code(404);
            return;
        }
        $data = require $file;
        if (!is_string($data) || $data === '') {
            http_response_code(404);
            return;
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        echo $data;
        exit;
    }
}
