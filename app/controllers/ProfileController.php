<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\PregnancyProfile;
use App\Models\User;
use App\Services\TimeService;

final class ProfileController extends Controller
{
    public function show(): string
    {
        $userId = $this->userId();
        return $this->view('profile/index', [
            'title'   => 'Profil Kehamilan',
            'nav'     => 'profile',
            'user'    => Auth::user(),
            'profile' => (new PregnancyProfile())->findByUser($userId) ?? [],
            'success' => Session::pullFlash('success'),
            'error'   => Session::pullFlash('error'),
            'errors'  => [],
        ]);
    }

    public function save(): string
    {
        $userId = $this->userId();
        $input = [
            'name'                => trim(strip_tags((string) Request::input('name', ''))),
            'nickname'            => trim(strip_tags((string) Request::input('nickname', ''))),
            'estimated_due_date'  => trim((string) Request::input('estimated_due_date', '')),
            'tracking_start_date' => trim((string) Request::input('tracking_start_date', '')),
            'notes'               => trim(strip_tags((string) Request::input('notes', ''))),
            'companion_name'      => trim(strip_tags((string) Request::input('companion_name', ''))),
            'companion_contact'   => trim(strip_tags((string) Request::input('companion_contact', ''))),
        ];
        $errors = [];

        if ($input['name'] === '' || mb_strlen($input['name']) > 100) {
            $errors['name'] = 'Nama akun wajib diisi (maksimal 100 karakter).';
        }
        if (mb_strlen($input['nickname']) > 60) {
            $errors['nickname'] = 'Nama panggilan maksimal 60 karakter.';
        }
        $edd = null;
        if ($input['estimated_due_date'] !== '') {
            $edd = TimeService::parseLocalDate($input['estimated_due_date']);
            $today = new \DateTimeImmutable('today', TimeService::local());
            if ($edd === null) {
                $errors['estimated_due_date'] = 'Tanggal HPL tidak valid.';
            } elseif ($edd < $today->modify('-60 days') || $edd > $today->modify('+300 days')) {
                $errors['estimated_due_date'] = 'HPL harus dalam rentang 60 hari lalu hingga 300 hari ke depan.';
            }
        }
        $tsd = null;
        if ($input['tracking_start_date'] !== '') {
            $tsd = TimeService::parseLocalDate($input['tracking_start_date']);
            if ($tsd === null) {
                $errors['tracking_start_date'] = 'Tanggal mulai pencatatan tidak valid.';
            }
        }
        if (mb_strlen($input['notes']) > 2000) {
            $errors['notes'] = 'Catatan maksimal 2000 karakter.';
        }
        if (mb_strlen($input['companion_name']) > 100) {
            $errors['companion_name'] = 'Nama pendamping maksimal 100 karakter.';
        }
        if ($input['companion_contact'] !== '' && !preg_match('/^[0-9+()\-\s]{6,30}$/', $input['companion_contact'])) {
            $errors['companion_contact'] = 'Kontak pendamping hanya boleh berisi angka, spasi, +, -, ( ).';
        }

        if ($errors) {
            http_response_code(422);
            return $this->view('profile/index', [
                'title'   => 'Profil Kehamilan',
                'nav'     => 'profile',
                'user'    => Auth::user(),
                'profile' => $input,
                'error'   => 'Periksa kembali isian Anda.',
                'errors'  => $errors,
                'success' => null,
            ]);
        }

        (new User())->updateName($userId, $input['name']);
        (new PregnancyProfile())->save($userId, [
            'nickname'            => $input['nickname'] ?: null,
            'estimated_due_date'  => $edd?->format('Y-m-d'),
            'tracking_start_date' => $tsd?->format('Y-m-d'),
            'notes'               => $input['notes'] ?: null,
            'companion_name'      => $input['companion_name'] ?: null,
            'companion_contact'   => $input['companion_contact'] ?: null,
        ]);
        Session::flash('success', 'Profil berhasil disimpan.');
        Response::redirect('profil');
    }

    /** Hapus akun beserta seluruh data (cascade). Wajib konfirmasi password. */
    public function deleteAccount(): void
    {
        $userId = $this->userId();
        $hash = (new User())->findPasswordHash($userId);
        if ($hash === null || !password_verify((string) Request::input('password', ''), $hash)) {
            Session::flash('error', 'Password salah. Akun tidak dihapus.');
            Response::redirect('profil');
        }
        if (Auth::isAdmin() && (new User())->countActiveAdmins($userId) < 1) {
            Session::flash('error', 'Anda adalah satu-satunya admin aktif. Jadikan pengguna lain admin terlebih dahulu.');
            Response::redirect('profil');
        }
        (new User())->delete($userId);
        Auth::logout();
        Session::start();
        Session::flash('success', 'Akun dan seluruh data Anda telah dihapus.');
        Response::redirect('login');
    }
}
