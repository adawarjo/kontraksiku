<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Status login berbasis session.
 * Sesi dianggap tidak berlaku bila akun dinonaktifkan atau session_version di
 * database sudah naik (password diganti/direset admin).
 */
final class Auth
{
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
            return self::$user = null;
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

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('session_version', (int) $user['session_version']);
        Session::forget('_csrf');
        self::refresh();
    }

    /** Muat ulang data pengguna dari database pada pemanggilan berikutnya. */
    public static function refresh(): void
    {
        self::$user = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        self::refresh();
        Session::destroy();
    }
}
