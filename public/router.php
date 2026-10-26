<?php

/*
 * Router untuk server bawaan PHP (pengembangan):
 *   php -S localhost:8000 -t public public/router.php
 * File statis yang ada dilayani langsung; selain itu diteruskan ke index.php.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file) && basename($file) !== 'router.php') {
    if (str_ends_with($file, '.webmanifest') || basename($file) === 'manifest.json') {
        header('Content-Type: application/manifest+json');
        readfile($file);
        return true;
    }
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
