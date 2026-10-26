<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        return View::render($template, $data, $layout);
    }

    protected function userId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            throw new HttpException(401, 'Sesi login berakhir. Silakan login kembali.');
        }
        return $id;
    }
}
