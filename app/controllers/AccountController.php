<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

/** Ganti password sendiri (termasuk wajib ganti setelah direset admin). */
final class AccountController extends Controller
{
    public function showChangePassword(): string
    {
        return $this->view('account/change-password', [
            'title'  => 'Ganti Password',
            'nav'    => 'profile',
            'forced' => Auth::mustChangePassword(),
            'errors' => [],
            'success' => Session::pullFlash('success'),
        ]);
    }

    public function changePassword(): string
    {
        $userId = $this->userId();
        try {
            $version = (new AuthService())->changePassword(
                $userId,
                (string) Request::input('current_password', ''),
                (string) Request::input('password', ''),
                (string) Request::input('password_confirmation', '')
            );
        } catch (HttpException $e) {
            if ($e->status !== 422) {
                throw $e;
            }
            http_response_code(422);
            return $this->view('account/change-password', [
                'title'  => 'Ganti Password',
                'nav'    => 'profile',
                'forced' => Auth::mustChangePassword(),
                'error'  => $e->getMessage(),
                'errors' => $e->errors,
                'success' => null,
            ]);
        }
        // Sesi di perangkat lain berakhir; sesi ini tetap login dengan ID sesi baru
        Session::regenerate();
        Session::set('session_version', $version);
        Auth::refresh();
        Auth::renewRemember();
        Session::flash('success', 'Password berhasil diganti. Sesi login di perangkat lain telah diakhiri.');
        Response::redirect('ganti-password');
    }
}
