<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;

/**
 * Manajemen pengguna oleh admin.
 *
 * Aturan:
 *  - Admin tidak dapat menonaktifkan, menurunkan peran, atau menghapus akunnya sendiri.
 *  - Sistem selalu menyisakan minimal satu admin aktif.
 *  - Reset password menghasilkan password sementara (ditampilkan sekali), memaksa pengguna
 *    menggantinya saat login, dan memutus semua sesi login pengguna tersebut.
 *  - Admin tidak memiliki akses ke data kontraksi/profil kehamilan pengguna lain.
 *  - Setiap tindakan dicatat di admin_audit_logs (tanpa password).
 */
final class UserAdminService
{
    private User $users;
    private AuditLog $audit;

    public function __construct(private int $adminId, private string $ip = '0.0.0.0')
    {
        $this->users = new User();
        $this->audit = new AuditLog();
    }

    /** @return array{id: int, temp_password: ?string} */
    public function create(string $name, string $email, string $role, ?string $password): array
    {
        $name = trim(strip_tags($name));
        $email = mb_strtolower(trim($email));
        $errors = AuthService::accountErrors($name, $email);
        if (!in_array($role, ['user', 'admin'], true)) {
            $errors['role'] = 'Peran tidak valid.';
        }
        $temp = null;
        if ($password === null || $password === '') {
            $temp = self::generatePassword();
            $password = $temp;
        } else {
            $errors += AuthService::passwordErrors($password, null);
        }
        if (!isset($errors['email']) && $this->users->emailExists($email)) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
        if ($errors) {
            throw new HttpException(422, 'Periksa kembali isian Anda.', $errors);
        }
        // Akun yang dibuat admin selalu wajib mengganti password saat login pertama
        $id = $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT), $role, true);
        $this->log('create', $id, $email, 'peran: ' . $role);
        return ['id' => $id, 'temp_password' => $temp];
    }

    public function update(int $id, string $name, string $email, string $role): array
    {
        $name = trim(strip_tags($name));
        $email = mb_strtolower(trim($email));
        $errors = AuthService::accountErrors($name, $email);
        if (!in_array($role, ['user', 'admin'], true)) {
            $errors['role'] = 'Peran tidak valid.';
        }
        if ($errors) {
            throw new HttpException(422, 'Periksa kembali isian Anda.', $errors);
        }
        return Database::transaction(function () use ($id, $name, $email, $role) {
            $this->users->lockAdmins();
            $target = $this->findTarget($id);
            if ($this->users->emailExists($email, $id)) {
                throw new HttpException(422, 'Email sudah dipakai akun lain.', ['email' => 'Email sudah dipakai akun lain.']);
            }
            if ($target['role'] === 'admin' && $role !== 'admin') {
                if ($id === $this->adminId) {
                    throw new HttpException(409, 'Anda tidak dapat menurunkan peran akun Anda sendiri.');
                }
                $this->assertAnotherActiveAdmin($id);
            }
            $this->users->updateAccount($id, $name, $email, $role);
            $changes = [];
            if ($target['name'] !== $name) $changes[] = 'nama';
            if ($target['email'] !== $email) $changes[] = 'email ' . $target['email'] . ' → ' . $email;
            if ($target['role'] !== $role) $changes[] = 'peran ' . $target['role'] . ' → ' . $role;
            $this->log('update', $id, $email, $changes ? implode(', ', $changes) : 'tanpa perubahan');
            return $this->users->find($id);
        });
    }

    public function setActive(int $id, bool $active): array
    {
        return Database::transaction(function () use ($id, $active) {
            $this->users->lockAdmins();
            $target = $this->findTarget($id);
            if (!$active) {
                if ($id === $this->adminId) {
                    throw new HttpException(409, 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
                }
                if ($target['role'] === 'admin') {
                    $this->assertAnotherActiveAdmin($id);
                }
            }
            $this->users->setActive($id, $active);
            $this->log($active ? 'activate' : 'deactivate', $id, $target['email'], null);
            return $this->users->find($id);
        });
    }

    /** @return string password sementara (tampilkan sekali ke admin) */
    public function resetPassword(int $id): string
    {
        $target = $this->findTarget($id);
        if ($id === $this->adminId) {
            throw new HttpException(409, 'Untuk akun Anda sendiri gunakan menu Ganti Password.');
        }
        $temp = self::generatePassword();
        $this->users->setPassword($id, password_hash($temp, PASSWORD_DEFAULT), true);
        (new LoginAttempt())->clear($target['email']); // buka kunci login bila sempat terkunci
        $this->log('reset_password', $id, $target['email'], 'password sementara dibuat, sesi lama diputus');
        return $temp;
    }

    public function delete(int $id): void
    {
        Database::transaction(function () use ($id) {
            $this->users->lockAdmins();
            $target = $this->findTarget($id);
            if ($id === $this->adminId) {
                throw new HttpException(409, 'Hapus akun Anda sendiri melalui halaman Profil.');
            }
            if ($target['role'] === 'admin' && $target['is_active']) {
                $this->assertAnotherActiveAdmin($id);
            }
            $this->users->delete($id);
            $this->log('delete', null, $target['email'], 'akun & seluruh data dihapus (id ' . $id . ')');
        });
    }

    /** Password sementara mudah dibaca: 3 kelompok 4 karakter, tanpa huruf/angka yang mirip. */
    public static function generatePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $groups = [];
        for ($g = 0; $g < 3; $g++) {
            $s = '';
            for ($i = 0; $i < 4; $i++) {
                $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $groups[] = $s;
        }
        return implode('-', $groups);
    }

    private function findTarget(int $id): array
    {
        $user = $this->users->find($id);
        if ($user === null) {
            throw new HttpException(404, 'Pengguna tidak ditemukan.');
        }
        return $user;
    }

    private function assertAnotherActiveAdmin(int $exceptId): void
    {
        if ($this->users->countActiveAdmins($exceptId) < 1) {
            throw new HttpException(409, 'Minimal harus ada satu admin aktif.');
        }
    }

    private function log(string $action, ?int $targetId, ?string $email, ?string $details): void
    {
        $this->audit->record($this->adminId, $action, $targetId, $email, $details, $this->ip);
    }
}
