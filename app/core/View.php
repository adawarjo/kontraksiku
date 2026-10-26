<?php

declare(strict_types=1);

namespace App\Core;

/** Render template PHP dari app/views dengan layout opsional. */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, $data + ['content' => $content]);
    }

    public static function capture(string $template, array $data): string
    {
        $file = APP_PATH . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View tidak ditemukan: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function partial(string $template, array $data = []): void
    {
        echo self::capture('partials/' . $template, $data);
    }
}
