<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'Admin/Layouts/app'): string
    {
        $file = base_path('app/Modules/' . $template . '.php');
        if (!is_file($file)) $file = base_path('resources/views/' . $template . '.php');
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = (string) ob_get_clean();

        if ($layout === '') {
            return $content;
        }
        $layoutFile = base_path('app/Modules/' . $layout . '.php');
        if (!is_file($layoutFile)) $layoutFile = base_path('resources/views/' . $layout . '.php');
        if (!is_file($layoutFile)) {
            return $content;
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $layoutFile;
        return (string) ob_get_clean();
    }

    public static function partial(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require base_path('app/Modules/' . $template . '.php');
    }
}
