<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;

/** Hanya pengunjung yang belum login (halaman login/registrasi). */
final class GuestMiddleware
{
    public function handle(): void
    {
        if (Auth::check()) {
            Response::redirect('');
        }
    }
}
