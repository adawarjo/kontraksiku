<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AuthService;
use App\Services\ContractionService;
use App\Services\UserAdminService;

echo "\n\033[1mAdmin & manajemen pengguna (layanan)\033[0m\n";

reset_test_database();
$users = new User();
$adminId = $users->create('Admin', 'admin@contoh.test', password_hash('rahasia123', PASSWORD_DEFAULT), 'admin');
$userId = make_user('ibu@contoh.test');
$svc = new UserAdminService($adminId, '127.0.0.1');

T::test('Admin membuat akun dengan password sementara; wajib ganti saat login', function () use ($svc, $users) {
    $r = $svc->create('Sari', 'Sari@Contoh.test', 'user', '');
    T::ok(preg_match('/^[A-Za-z2-9]{4}-[A-Za-z2-9]{4}-[A-Za-z2-9]{4}$/', $r['temp_password']) === 1, 'format password sementara');
    $u = $users->find($r['id']);
    T::eq('sari@contoh.test', $u['email']);
    T::eq(true, $u['must_change_password']);
    $login = (new AuthService())->attempt('sari@contoh.test', $r['temp_password'], '1.1.1.1');
    T::eq(true, $login['must_change_password']);
    T::throwsHttp(422, fn () => $svc->create('X', 'sari@contoh.test', 'user', ''), 'email ganda');
    T::throwsHttp(422, fn () => $svc->create('X', 'x@contoh.test', 'superuser', ''), 'peran tidak valid');
    T::throwsHttp(422, fn () => $svc->create('X', 'x@contoh.test', 'user', '123'), 'password pendek');
});

T::test('Reset password: password lama tidak berlaku, sesi lama diputus, kunci login dibuka', function () use ($svc, $users, $userId) {
    $auth = new AuthService();
    for ($i = 0; $i < 5; $i++) {
        try { $auth->attempt('ibu@contoh.test', 'salah', '2.2.2.2'); } catch (App\Core\HttpException) {}
    }
    T::throwsHttp(429, fn () => $auth->attempt('ibu@contoh.test', 'rahasia123', '2.2.2.2'), 'terkunci');
    $before = $users->find($userId)['session_version'];
    $temp = $svc->resetPassword($userId);
    $after = $users->find($userId);
    T::eq($before + 1, $after['session_version'], 'session_version naik');
    T::eq(true, $after['must_change_password']);
    T::throwsHttp(422, fn () => $auth->attempt('ibu@contoh.test', 'rahasia123', '2.2.2.3'), 'password lama ditolak');
    $auth->attempt('ibu@contoh.test', $temp, '2.2.2.2'); // kunci sudah dibuka
});

T::test('Pengguna mengganti password sendiri; flag wajib ganti hilang', function () use ($users, $userId) {
    $auth = new AuthService();
    $temp = (new UserAdminService(1, 'x'))->resetPassword($userId);
    T::throwsHttp(422, fn () => $auth->changePassword($userId, 'salah', 'passwordbaru1', 'passwordbaru1'), 'password saat ini salah');
    T::throwsHttp(422, fn () => $auth->changePassword($userId, $temp, $temp, $temp), 'harus berbeda');
    T::throwsHttp(422, fn () => $auth->changePassword($userId, $temp, 'passwordbaru1', 'beda'), 'konfirmasi');
    $auth->changePassword($userId, $temp, 'passwordbaru1', 'passwordbaru1');
    T::eq(false, $users->find($userId)['must_change_password']);
    $auth->attempt('ibu@contoh.test', 'passwordbaru1', '3.3.3.3');
});

T::test('Nonaktifkan akun: login ditolak dengan pesan jelas; aktifkan kembali', function () use ($svc, $userId) {
    $svc->setActive($userId, false);
    $e = T::throwsHttp(422, fn () => (new AuthService())->attempt('ibu@contoh.test', 'passwordbaru1', '4.4.4.4'));
    T::ok(str_contains($e->getMessage(), 'dinonaktifkan'), 'pesan nonaktif');
    T::throwsHttp(422, fn () => (new AuthService())->attempt('ibu@contoh.test', 'salah', '4.4.4.5'), 'password salah tetap pesan umum');
    $svc->setActive($userId, true);
    (new AuthService())->attempt('ibu@contoh.test', 'passwordbaru1', '4.4.4.4');
});

T::test('Admin tidak dapat menonaktifkan/menurunkan/menghapus/mereset dirinya sendiri', function () use ($svc, $adminId) {
    T::throwsHttp(409, fn () => $svc->setActive($adminId, false));
    T::throwsHttp(409, fn () => $svc->update($adminId, 'Admin', 'admin@contoh.test', 'user'));
    T::throwsHttp(409, fn () => $svc->delete($adminId));
    T::throwsHttp(409, fn () => $svc->resetPassword($adminId));
});

T::test('Selalu tersisa minimal satu admin aktif', function () use ($users, $adminId) {
    $second = $users->create('Admin 2', 'admin2@contoh.test', password_hash('rahasia123', PASSWORD_DEFAULT), 'admin');
    $svc2 = new UserAdminService($second, '127.0.0.1');
    $svc2->update($adminId, 'Admin', 'admin@contoh.test', 'user'); // admin 2 menurunkan admin 1: boleh (masih ada admin 2)
    T::eq(1, $users->countActiveAdmins());
    $svcBack = new UserAdminService($second, '127.0.0.1');
    T::throwsHttp(409, fn () => $svcBack->update($second, 'Admin 2', 'admin2@contoh.test', 'user'), 'menurunkan diri sendiri');
    $svcBack->update($adminId, 'Admin', 'admin@contoh.test', 'admin'); // kembalikan
    T::eq(2, $users->countActiveAdmins());
    // Menghapus admin lain diperbolehkan selama masih ada admin aktif
    (new UserAdminService($adminId, 'x'))->delete($second);
    T::eq(1, $users->countActiveAdmins());
});

T::test('Ubah akun: validasi email unik & format', function () use ($svc, $userId) {
    T::throwsHttp(422, fn () => $svc->update($userId, 'Ibu', 'admin@contoh.test', 'user'), 'email dipakai');
    T::throwsHttp(422, fn () => $svc->update($userId, '', 'bukan-email', 'user'), 'format');
    T::throwsHttp(404, fn () => $svc->update(99999, 'X', 'x@contoh.test', 'user'), 'tidak ada');
    $u = $svc->update($userId, 'Ibu Baru', 'IBU2@contoh.test', 'user');
    T::eq('ibu2@contoh.test', $u['email']);
});

T::test('Hapus pengguna menghapus seluruh datanya (cascade) dan tercatat di audit', function () use ($svc) {
    $id = make_user('hapus@contoh.test');
    (new ContractionService())->sync($id, uuid4(), ago(100), ago(100) + 30_000);
    $svc->delete($id);
    T::eq(0, db_count('SELECT COUNT(*) FROM users WHERE id = ?', [$id]));
    T::eq(0, db_count('SELECT COUNT(*) FROM contractions WHERE user_id = ?', [$id]));
    T::eq(1, db_count("SELECT COUNT(*) FROM admin_audit_logs WHERE action = 'delete' AND target_email = 'hapus@contoh.test'"));
});

T::test('Log audit tidak pernah menyimpan password', function () {
    $stmt = App\Core\Database::connection()->query('SELECT CONCAT_WS(" ", action, target_email, details) FROM admin_audit_logs');
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $line) {
        T::ok(!preg_match('/[A-Za-z2-9]{4}-[A-Za-z2-9]{4}-[A-Za-z2-9]{4}/', $line), 'tidak ada password sementara: ' . $line);
        T::ok(!str_contains($line, 'rahasia123') && !str_contains($line, 'passwordbaru1'), 'tidak ada password');
    }
    T::ok(db_count('SELECT COUNT(*) FROM admin_audit_logs') >= 8, 'tindakan tercatat');
});
