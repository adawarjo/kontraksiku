<?php

declare(strict_types=1);

/*
 * Bootstrap aplikasi: autoloader, konfigurasi, zona waktu, penanganan error.
 * Dipanggil oleh public/index.php dan oleh skrip pengujian.
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// Autoloader sederhana: App\Controllers\AuthController -> app/controllers/AuthController.php
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $parts = explode('\\', substr($class, 4));
    $file  = array_pop($parts);
    $dir   = implode('/', array_map('strtolower', $parts));
    $path  = APP_PATH . '/' . ($dir !== '' ? $dir . '/' : '') . $file . '.php';
    if (is_file($path)) {
        require $path;
    }
});

App\Core\Env::load(getenv('KK_ENV_FILE') ?: BASE_PATH . '/.env');

$GLOBALS['__config'] = [
    'app'      => require BASE_PATH . '/config/app.php',
    'database' => require BASE_PATH . '/config/database.php',
];

/** Ambil nilai konfigurasi dengan notasi titik, mis. config('app.name'). */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__config'];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/** Escape HTML untuk mencegah XSS. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absolut-path relatif terhadap base aplikasi. */
function url(string $path = ''): string
{
    return App\Core\Request::basePath() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $ver  = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $ver;
}

// Semua perhitungan internal memakai UTC; tampilan dikonversi ke zona waktu aplikasi.
date_default_timezone_set('UTC');

ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
