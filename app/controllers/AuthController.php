<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function showLogin(): string
    {
        return $this->view('auth/login', [
            'title'   => 'Masuk',
            'success' => Session::pullFlash('success'),
            'allowRegistration' => (bool) config('app.allow_registration'),
        ], 'layouts/auth');
    }

    public function login(): string
    {
        $email = (string) Request::input('email', '');
        try {
            $user = (new AuthService())->attempt($email, (string) Request::input('password', ''), Request::ip());
        } catch (HttpException $e) {
            if (!in_array($e->status, [422, 429], true)) {
                throw $e;
            }
            http_response_code($e->status);
            return $this->view('auth/login', [
                'title' => 'Masuk',
                'error' => $e->getMessage(),
                'old'   => ['email' => $email],
                'allowRegistration' => (bool) config('app.allow_registration'),
            ], 'layouts/auth');
        }
        Auth::login($user);
        Response::redirect('');
    }

    public function showRegister(): string
    {
        $this->ensureRegistrationOpen();
        return $this->view('auth/register', ['title' => 'Daftar'], 'layouts/auth');
    }

    public function register(): string
    {
        $this->ensureRegistrationOpen();
        $old = ['name' => (string) Request::input('name', ''), 'email' => (string) Request::input('email', '')];
        try {
            (new AuthService())->register(
                $old['name'],
                $old['email'],
                (string) Request::input('password', ''),
                (string) Request::input('password_confirmation', '')
            );
        } catch (HttpException $e) {
            if ($e->status !== 422) {
                throw $e;
            }
            http_response_code(422);
            return $this->view('auth/register', [
                'title'  => 'Daftar',
                'error'  => $e->getMessage(),
                'errors' => $e->errors,
                'old'    => $old,
            ], 'layouts/auth');
        }
        Session::flash('success', 'Pendaftaran berhasil. Silakan masuk.');
        Response::redirect('login');
    }

    public function logout(): void
    {
        Auth::logout();
        Session::start();
        Session::flash('success', 'Anda telah keluar.');
        Response::redirect('login');
    }

    private function ensureRegistrationOpen(): void
    {
        if (!config('app.allow_registration')) {
            throw new HttpException(404, 'Pendaftaran akun baru sedang ditutup.');
        }
    }
}
