<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use Throwable;

/** Router sederhana: pencocokan path persis + middleware. */
final class Router
{
    private array $routes = [];

    private const MIDDLEWARE = [
        'auth'  => AuthMiddleware::class,
        'admin' => AdminMiddleware::class,
        'guest' => GuestMiddleware::class,
        'csrf'  => CsrfMiddleware::class,
    ];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        // Semua POST wajib lolos verifikasi CSRF
        $this->add('POST', $path, $handler, array_values(array_unique([...$middleware, 'csrf'])));
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[$method][rtrim($path, '/') ?: '/'] = [$handler, $middleware];
    }

    public function dispatch(): void
    {
        Response::sendSecurityHeaders();
        try {
            Session::start();
            $method = Request::method();
            $path   = Request::path();

            $route = $this->routes[$method][$path] ?? null;
            if ($route === null) {
                $allowed = array_keys(array_filter($this->routes, static fn ($r) => isset($r[$path])));
                if ($allowed) {
                    header('Allow: ' . implode(', ', $allowed));
                    throw new HttpException(405, 'Metode tidak diizinkan.');
                }
                throw new HttpException(404, 'Halaman tidak ditemukan.');
            }

            [$handler, $middleware] = $route;
            foreach ($middleware as $name) {
                (new (self::MIDDLEWARE[$name])())->handle();
            }

            [$class, $action] = $handler;
            $output = (new $class())->$action();
            if (is_string($output)) {
                header('Content-Type: text/html; charset=utf-8');
                echo $output;
            }
        } catch (HttpException $e) {
            $this->renderError($e->status, $e->getMessage(), $e->errors, $e->extra);
        } catch (Throwable $e) {
            Logger::error('Unhandled exception', $e);
            $this->renderError(500, 'Terjadi kesalahan pada server. Silakan coba lagi.');
        }
    }

    private function renderError(int $status, string $message, array $errors = [], array $extra = []): void
    {
        if (Request::isApi() || str_contains((string) Request::header('Accept'), 'application/json')) {
            Response::json(['ok' => false, 'message' => $message, 'errors' => $errors] + $extra, $status);
        }
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        try {
            echo View::render('errors/error', ['status' => $status, 'message' => $message, 'title' => 'Kesalahan'], 'layouts/auth');
        } catch (Throwable) {
            echo '<h1>' . $status . '</h1><p>' . e($message) . '</p>';
        }
    }
}
