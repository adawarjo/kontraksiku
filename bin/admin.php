<?php

declare(strict_types=1);

/*
 * Alat baris perintah untuk admin (dijalankan di server, bukan dari browser).
 *
 *   php bin/admin.php create <email> "<Nama>"   Buat akun admin baru (password sementara ditampilkan)
 *   php bin/admin.php promote <email>           Jadikan pengguna yang sudah ada sebagai admin
 *   php bin/admin.php demote <email>            Turunkan admin menjadi pengguna biasa
 *   php bin/admin.php reset-password <email>    Reset password (mis. admin lupa password)
 *   php bin/admin.php list                      Tampilkan daftar admin
 *
 * Windows/XAMPP: C:\xampp\php\php.exe bin\admin.php create admin@contoh.com "Admin"
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuthService;
use App\Services\UserAdminService;

$users = new User();
$audit = new AuditLog();
$cmd = $argv[1] ?? '';
$email = mb_strtolower(trim($argv[2] ?? ''));

function fail(string $msg): never
{
    fwrite(STDERR, "Gagal: $msg\n");
    exit(1);
}

function findUser(User $users, string $email): array
{
    if ($email === '') {
        fail('email wajib diisi.');
    }
    $row = $users->findForAuth($email);
    if ($row === null) {
        fail("pengguna $email tidak ditemukan.");
    }
    return $row;
}

try {
    switch ($cmd) {
        case 'create':
            $name = trim($argv[3] ?? 'Admin');
            $errors = AuthService::accountErrors($name, $email);
            if ($errors) {
                fail(implode(' ', $errors));
            }
            if ($users->emailExists($email)) {
                fail("email $email sudah terdaftar. Gunakan perintah promote.");
            }
            $temp = UserAdminService::generatePassword();
            $id = $users->create($name, $email, password_hash($temp, PASSWORD_DEFAULT), 'admin', true);
            $audit->record(null, 'create', $id, $email, 'admin dibuat via CLI', 'cli');
            echo "Admin dibuat: $email\nPassword sementara: $temp\n(wajib diganti saat login pertama)\n";
            break;

        case 'promote':
        case 'demote':
            $user = findUser($users, $email);
            if ($cmd === 'demote' && $users->countActiveAdmins($user['id']) < 1) {
                fail('minimal harus ada satu admin aktif.');
            }
            $role = $cmd === 'promote' ? 'admin' : 'user';
            $users->updateAccount($user['id'], $user['name'], $user['email'], $role);
            if ($cmd === 'promote' && !$user['is_active']) {
                $users->setActive($user['id'], true);
            }
            $audit->record(null, 'update', $user['id'], $email, "peran → $role via CLI", 'cli');
            echo "Peran $email sekarang: $role\n";
            break;

        case 'reset-password':
            $user = findUser($users, $email);
            $temp = UserAdminService::generatePassword();
            $users->setPassword($user['id'], password_hash($temp, PASSWORD_DEFAULT), true);
            (new LoginAttempt())->clear($email);
            $audit->record(null, 'reset_password', $user['id'], $email, 'via CLI', 'cli');
            echo "Password sementara untuk $email: $temp\n(wajib diganti saat login)\n";
            break;

        case 'list':
            $rows = $users->search('', 'admin', '', 100, 0)['rows'];
            if (!$rows) {
                echo "Belum ada admin. Buat dengan: php bin/admin.php create <email> \"<Nama>\"\n";
            }
            foreach ($rows as $r) {
                printf("%-5d %-35s %-25s %s\n", $r['id'], $r['email'], $r['name'], $r['is_active'] ? 'aktif' : 'nonaktif');
            }
            break;

        default:
            echo "Penggunaan:\n"
                . "  php bin/admin.php create <email> \"<Nama>\"\n"
                . "  php bin/admin.php promote <email>\n"
                . "  php bin/admin.php demote <email>\n"
                . "  php bin/admin.php reset-password <email>\n"
                . "  php bin/admin.php list\n";
            exit($cmd === '' ? 0 : 1);
    }
} catch (PDOException $e) {
    fail('koneksi/permintaan database gagal. Periksa .env dan pastikan migrasi sudah dijalankan. (' . $e->getMessage() . ')');
}
