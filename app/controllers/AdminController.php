<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\UserAdminService;

/**
 * Manajemen pengguna (khusus admin). Semua aksi memakai form POST + CSRF,
 * hasil ditampilkan lewat flash message (SweetAlert2 di halaman).
 */
final class AdminController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): string
    {
        $q = mb_substr((string) Request::query('q', ''), 0, 100);
        $role = (string) Request::query('peran', '');
        $status = (string) Request::query('status', '');
        $page = max(1, (int) filter_var(Request::query('hal', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));

        $users = new User();
        $result = $users->search($q, $role, $status, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $pages = max(1, (int) ceil($result['total'] / self::PER_PAGE));

        return $this->view('admin/users', [
            'title'   => 'Manajemen Pengguna',
            'nav'     => 'admin',
            'me'      => Auth::user(),
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => min($page, $pages),
            'pages'   => $pages,
            'counts'  => $users->counts(),
            'filters' => ['q' => $q, 'peran' => $role, 'status' => $status],
            'audit'   => (new AuditLog())->recent(15),
            'flash'   => Session::pullFlash('admin'),
            'allowRegistration' => (bool) config('app.allow_registration'),
            'scripts' => ['js/admin.js'],
        ]);
    }

    public function store(): void
    {
        $this->run(function (UserAdminService $svc) {
            $result = $svc->create(
                (string) Request::input('name', ''),
                (string) Request::input('email', ''),
                (string) Request::input('role', 'user'),
                (string) Request::input('password', '')
            );
            $user = (new User())->find($result['id']);
            return [
                'type'    => 'success',
                'title'   => 'Pengguna ditambahkan',
                'message' => sprintf('Akun %s (%s) berhasil dibuat. Pengguna wajib mengganti password saat login pertama.', $user['name'], $user['email']),
                'password' => $result['temp_password'],
                'email'   => $user['email'],
            ];
        });
    }

    public function update(): void
    {
        $this->run(function (UserAdminService $svc) {
            $user = $svc->update(
                $this->targetId(),
                (string) Request::input('name', ''),
                (string) Request::input('email', ''),
                (string) Request::input('role', 'user')
            );
            return ['type' => 'success', 'title' => 'Tersimpan', 'message' => 'Data akun ' . $user['email'] . ' diperbarui.'];
        });
    }

    public function toggleActive(): void
    {
        $this->run(function (UserAdminService $svc) {
            $active = Request::input('active') === '1';
            $user = $svc->setActive($this->targetId(), $active);
            return [
                'type'    => 'success',
                'title'   => $active ? 'Akun diaktifkan' : 'Akun dinonaktifkan',
                'message' => $active
                    ? $user['email'] . ' dapat login kembali.'
                    : $user['email'] . ' tidak dapat login dan semua sesinya telah diakhiri.',
            ];
        });
    }

    public function resetPassword(): void
    {
        $this->run(function (UserAdminService $svc) {
            $id = $this->targetId();
            $password = $svc->resetPassword($id);
            $user = (new User())->find($id);
            return [
                'type'     => 'success',
                'title'    => 'Password direset',
                'message'  => 'Berikan password sementara ini kepada ' . $user['name'] . '. Password hanya ditampilkan sekali dan wajib diganti saat login.',
                'password' => $password,
                'email'    => $user['email'],
            ];
        });
    }

    public function destroy(): void
    {
        $this->run(function (UserAdminService $svc) {
            $id = $this->targetId();
            $email = (new User())->find($id)['email'] ?? '';
            $svc->delete($id);
            return ['type' => 'success', 'title' => 'Pengguna dihapus', 'message' => 'Akun ' . $email . ' beserta seluruh datanya telah dihapus.'];
        });
    }

    /** Jalankan aksi, simpan hasil/eror sebagai flash, kembali ke daftar dengan filter yang sama. */
    private function run(callable $action): never
    {
        $svc = new UserAdminService($this->userId(), Request::ip());
        try {
            $flash = $action($svc);
        } catch (HttpException $e) {
            if ($e->status >= 500) {
                throw $e;
            }
            $flash = [
                'type'    => 'error',
                'title'   => 'Gagal',
                'message' => $e->getMessage() . ($e->errors ? ' ' . implode(' ', $e->errors) : ''),
            ];
        }
        Session::flash('admin', $flash);
        $back = (string) Request::input('_back', '');
        // Hanya izinkan kembali ke halaman admin dengan query aman (cegah open redirect)
        $query = preg_match('/^[a-zA-Z0-9=&%._\-+@]*$/', $back) ? $back : '';
        Response::redirect('admin' . ($query !== '' ? '?' . $query : ''));
    }

    private function targetId(): int
    {
        $id = filter_var(Request::input('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HttpException(422, 'ID pengguna tidak valid.');
        }
        return $id;
    }
}
