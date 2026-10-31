<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Services\RememberMeService;

/**
 * Status login berbasis session.
 * Sesi dianggap tidak berlaku bila akun dinonaktifkan atau session_version di
 * database sudah naik (password diganti/direset admin).
 * Bila session kosong (browser ditutup / timeout) tetapi perangkat dipercaya
 * ("ingat saya"), session baru dibuat otomatis dari cookie token.
 */
final class Auth
{
    private const REMEMBER_COOKIE = 'KKREMEMBER';

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = Session::get('user_id');
        if (!is_int($id)) {
            return self::$user = self::userFromRememberCookie();
        }
        $user = (new User())->find($id);
        if ($user === null || !$user['is_active'] || $user['session_version'] !== Session::get('session_version')) {
            // Akun dihapus/dinonaktifkan atau password berubah di tempat lain -> paksa login ulang
            Session::forget('user_id');
            Session::forget('session_version');
            return self::$user = null;
        }
        return self::$user = $user;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    public static function mustChangePassword(): bool
    {
        return (bool) (self::user()['must_change_password'] ?? false);
    }

    /** $remember = true: percayakan perangkat ini selama RememberMeService::days() hari. */
    public static function login(array $user, bool $remember = false): void
    {
        self::startSession($user);
        if ($remember) {
            self::remember($user);
        } else {
            self::forgetRememberCookie();
        }
        self::refresh();
    }

    /**
     * Setelah password diganti: token lama perangkat ini ikut tidak berlaku
     * (session_version naik), jadi terbitkan token baru bila perangkat dipercaya.
     */
    public static function renewRemember(): void
    {
        $user = self::user();
        if ($user !== null && isset($_COOKIE[self::REMEMBER_COOKIE])) {
            self::remember($user);
        }
    }

    /** Muat ulang data pengguna dari database pada pemanggilan berikutnya. */
    public static function refresh(): void
    {
        self::$user = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        self::forgetRememberCookie();
        self::refresh();
        Session::destroy();
    }

    private static function startSession(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('session_version', (int) $user['session_version']);
        Session::forget('_csrf');
    }

    private static function userFromRememberCookie(): ?array
    {
        $value = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if (!is_string($value) || headers_sent()) {
            return null;
        }
        $user = (new RememberMeService())->resolve($value);
        if ($user === null) {
            self::forgetRememberCookie();
            return null;
        }
        self::startSession($user);
        (new User())->touchLogin($user['id']);
        return $user;
    }

    private static function remember(array $user): void
    {
        $service = new RememberMeService();
        $old = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if (is_string($old)) {
            $service->revoke($old);
        }
        $token = $service->issue($user);
        self::setRememberCookie($token['value'], $token['expires']);
        $_COOKIE[self::REMEMBER_COOKIE] = $token['value'];
    }

    /** Cabut token perangkat ini (bila ada) dan hapus cookie-nya. */
    private static function forgetRememberCookie(): void
    {
        $value = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if ($value === null) {
            return;
        }
        if (is_string($value)) {
            (new RememberMeService())->revoke($value);
        }
        unset($_COOKIE[self::REMEMBER_COOKIE]);
        self::setRememberCookie('', time() - 3600);
    }

    private static function setRememberCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => Request::basePath() . '/',
            'secure'   => Request::isHttps() || (bool) config('app.session_secure'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
