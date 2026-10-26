<?php

declare(strict_types=1);

namespace App\Controllers;

final class PageController extends Controller
{
    public function info(): string
    {
        return $this->view('pages/info', ['title' => 'Informasi Keselamatan', 'nav' => 'info']);
    }
}
