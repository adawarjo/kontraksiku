<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;

/** Hanya admin aktif. Dipasang setelah middleware 'auth'. */
final class AdminMiddleware
{
    public function handle(): void
    {
        if (!Auth::isAdmin()) {
            throw new HttpException(403, 'Halaman ini hanya untuk admin.');
        }
    }
}
