<?php
declare(strict_types=1);

namespace PIZ;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        $file = PIZ_ROOT . '/src/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Шаблон не найден: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = (string) ob_get_clean();

        if ($layout === null || $layout === '') {
            echo $content;
            return;
        }

        $layoutFile = PIZ_ROOT . '/src/Views/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException('Макет не найден: ' . $layout);
        }
        require $layoutFile;
    }

    public static function partial(string $view, array $data = []): void
    {
        $file = PIZ_ROOT . '/src/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Шаблон не найден: ' . $view);
        }
        extract($data, EXTR_SKIP);
        require $file;
    }

    public static function capture(string $view, array $data = []): string
    {
        $file = PIZ_ROOT . '/src/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Шаблон не найден: ' . $view);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
