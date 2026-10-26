<?php

declare(strict_types=1);

/*
 * Front controller KontraksiKu.
 * Hanya folder public/ yang boleh diakses dari web.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new App\Core\Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch();
