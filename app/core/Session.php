<?php

declare(strict_types=1);

namespace App\Core;

/** Manajemen session PHP yang aman. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $lifetimeSeconds = max(30, (int) config('app.session_lifetime')) * 60;
        $savePath = BASE_PATH . '/storage/sessions';
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) $lifetimeSeconds);
        session_name('KKSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Request::basePath() . '/',
            'secure'   => Request::isHttps() || (bool) config('app.session_secure'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Timeout karena tidak aktif
        $now  = time();
        $last = $_SESSION['_last_activity'] ?? $now;
        if ($now - $last > $lifetimeSeconds) {
            self::destroy();
            session_start();
        }
        $_SESSION['_last_activity'] = $now;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $params['path'],
                'secure'   => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
    }
}
