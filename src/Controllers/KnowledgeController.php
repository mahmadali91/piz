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
}
