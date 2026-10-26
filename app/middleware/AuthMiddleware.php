<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Hanya pengguna yang sudah login.
 * Bila password direset admin, pengguna diarahkan ke halaman ganti password
 * sebelum dapat memakai fitur lain.
 */
final class AuthMiddleware
{
    /** Rute yang tetap boleh diakses saat wajib mengganti password. */
    private const ALLOWED_WHEN_MUST_CHANGE = ['/ganti-password', '/logout', '/api/csrf'];

    public function handle(): void
    {
        if (!Auth::check()) {
            if (Request::isApi()) {
                throw new HttpException(401, 'Sesi login berakhir. Silakan login kembali.');
            }
            Response::redirect('login');
        }
        if (Auth::mustChangePassword() && !in_array(Request::path(), self::ALLOWED_WHEN_MUST_CHANGE, true)) {
            if (Request::isApi()) {
                throw new HttpException(403, 'Anda harus mengganti password terlebih dahulu.');
            }
            Response::redirect('ganti-password');
        }
    }
}
