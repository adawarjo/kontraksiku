<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Models\LoginAttempt;
use App\Models\User;

/** Registrasi dan login dengan pembatasan percobaan. */
final class AuthService
{
    private User $users;
    private LoginAttempt $attempts;

    public function __construct()
    {
        $this->users = new User();
        $this->attempts = new LoginAttempt();
    }

    /**
     * @return array user (tanpa hash password)
     * @throws HttpException 429 bila terlalu banyak percobaan, 422 bila kredensial salah
     */
    public function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));
        $max = max(1, (int) config('app.login_max_attempts', 5));
        $lock = max(1, (int) config('app.login_lock_minutes', 15));

        $counts = $this->attempts->countRecent($email, $ip, $lock);
        // Batas per IP lebih longgar (beberapa pengguna bisa berbagi jaringan)
        if ($counts['email'] >= $max || $counts['ip'] >= $max * 4) {
            throw new HttpException(429, sprintf('Terlalu banyak percobaan login. Coba lagi dalam %d menit.', $lock));
        }

        $user = $email !== '' ? $this->users->findForAuth($email) : null;
        // password_verify tetap dijalankan agar waktu respons tidak membocorkan keberadaan email
        $hash = $user['password'] ?? '$2y$10$LnuwBVXgCvkOPLBgYa83nuPQ46SbJWKR0c1rXp0oJ2Wdm2tK.1s9S';
        $valid = password_verify($password, $hash) && $user !== null;

        if (!$valid) {
            $this->attempts->record($email, $ip);
            throw new HttpException(422, 'Email atau password salah.');
        }

        // Status nonaktif hanya diungkap setelah password benar (tidak membocorkan keberadaan akun)
        if (!$user['is_active']) {
            throw new HttpException(422, 'Akun Anda dinonaktifkan. Hubungi admin aplikasi.');
        }

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        $this->attempts->clear($email);
        $this->attempts->purgeOlderThan(24 * 60);
        $this->users->touchLogin((int) $user['id']);
        unset($user['password']);
        return $user;
    }

    /**
     * Ganti password sendiri (wajib memasukkan password saat ini).
     * @return int session_version baru
     */
    public function changePassword(int $userId, string $current, string $new, string $confirm): int
    {
        $hash = $this->users->findPasswordHash($userId);
        $errors = [];
        if ($hash === null || !password_verify($current, $hash)) {
            $errors['current_password'] = 'Password saat ini salah.';
        }
        $errors += self::passwordErrors($new, $confirm);
        if (!isset($errors['password']) && $hash !== null && password_verify($new, $hash)) {
            $errors['password'] = 'Password baru harus berbeda dari password lama.';
        }
        if ($errors) {
            throw new HttpException(422, 'Periksa kembali isian Anda.', $errors);
        }
        $this->users->setPassword($userId, password_hash($new, PASSWORD_DEFAULT), false);
        return (int) $this->users->find($userId)['session_version'];
    }

    /** Validasi password baru (dipakai registrasi, ganti password, admin). */
    public static function passwordErrors(string $password, ?string $confirm): array
    {
        $errors = [];
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors['password'] = 'Password minimal 8 karakter (maksimal 72).';
        }
        if ($confirm !== null && !hash_equals($password, $confirm)) {
            $errors['password_confirmation'] = 'Konfirmasi password tidak sama.';
        }
        return $errors;
    }

    /** Validasi nama & email akun. */
    public static function accountErrors(string $name, string $email): array
    {
        $errors = [];
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Nama wajib diisi (maksimal 100 karakter).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors['email'] = 'Format email tidak valid.';
        }
        return $errors;
    }

    /** @return int ID pengguna baru */
    public function register(string $name, string $email, string $password, string $confirm): int
    {
        $name = trim(strip_tags($name));
        $email = mb_strtolower(trim($email));
        $errors = self::accountErrors($name, $email) + self::passwordErrors($password, $confirm);
        if (!isset($errors['email']) && $this->users->emailExists($email)) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
        if ($errors) {
            throw new HttpException(422, 'Periksa kembali isian Anda.', $errors);
        }
        return $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT));
    }
}
