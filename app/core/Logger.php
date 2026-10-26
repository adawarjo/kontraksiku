<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/** Logger file sederhana. Log berada di storage/logs (di luar folder public). */
final class Logger
{
    public static function error(string $message, ?Throwable $e = null): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] %s", gmdate('Y-m-d H:i:s'), $message);
        if ($e !== null) {
            $line .= sprintf(' | %s: %s @ %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        }
        @file_put_contents($dir . '/app-' . gmdate('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
