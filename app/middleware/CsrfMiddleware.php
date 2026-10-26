<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;

/** Verifikasi token CSRF dari field form `_csrf` atau header `X-CSRF-Token`. */
final class CsrfMiddleware
{
    public function handle(): void
    {
        $token = Request::header('X-CSRF-Token') ?? ($_POST['_csrf'] ?? null);
        if (!Csrf::verify(is_string($token) ? $token : null)) {
            throw new HttpException(419, 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        }
    }
}
