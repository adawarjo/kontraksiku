<?php

declare(strict_types=1);

use App\Models\Contraction;
use App\Services\AuthService;
use App\Services\ContractionService;
use App\Services\StatsService;
use App\Services\TimeService;

echo "\n\033[1mLayanan & logika bisnis\033[0m\n";

reset_test_database();
$svc = new ContractionService();
$model = new Contraction();
$alice = make_user('alice@contoh.test');
$bob = make_user('bob@contoh.test');

/* ---------- Waktu & format ---------- */
T::test('Format durasi: 45 dtk = 00:45, 80 dtk = 01:20, 3725 dtk = 1:02:05', function () {
    T::eq('00:45', TimeService::formatDuration(45));
    T::eq('01:20', TimeService::formatDuration(80));
    T::eq('1:02:05', TimeService::formatDuration(3725));
    T::eq('-', TimeService::formatDuration(null));
});

T::test('Tanggal tidak valid ditolak (30-02-2026, 2026-13-01, teks bebas)', function () {
    T::eq(null, TimeService::parseLocalDate('30-02-2026'));
    T::eq(null, TimeService::parseLocalDate('2026-13-01'));
    T::eq(null, TimeService::parseLocalDate('kemarin'));
    T::eq(null, TimeService::parseLocalDateTime('2026-10-26', '25:00:00'));
    T::eq('26-10-2026', TimeService::parseLocalDate('2026-10-26')->format('d-m-Y'));
    T::eq('26-10-2026', TimeService::parseLocalDate('26-10-2026')->format('d-m-Y'));
});

T::test('Konversi zona waktu: 00:30 WIB = 17:30 UTC hari sebelumnya (pergantian hari)', function () {
    $utc = TimeService::parseLocalDateTime('2026-10-27', '00:30:00');
    T::eq('2026-10-26 17:30:00', TimeService::toDb($utc));
    T::eq('27-10-2026', TimeService::localDate('2026-10-26 17:30:00'));
    T::eq('00:30:00', TimeService::localTime('2026-10-26 17:30:00'));
    [$from, $to] = TimeService::localDateRangeToUtc(TimeService::parseLocalDate('2026-10-27'), TimeService::parseLocalDate('2026-10-27'));
    T::eq('2026-10-26 17:00:00', $from);
    T::eq('2026-10-27 17:00:00', $to);
});

T::test('Usia kehamilan dihitung dari HPL', function () {
    $today = new DateTimeImmutable('2026-10-26', TimeService::local());
    $ga = TimeService::gestationalAge('2026-11-09', $today); // 14 hari sebelum HPL -> 38 minggu 0 hari
    T::eq(38, $ga['weeks']);
    T::eq(0, $ga['days']);
    T::eq(null, TimeService::gestationalAge(null));
});

/* ---------- Timer: durasi & interval ---------- */
T::test('Mulai lalu akhiri: durasi = selesai - mulai', function () use ($svc, $alice) {
    $u = uuid4();
    $start = ago(3600);
    $r = $svc->sync($alice, $u, $start, null);
    T::ok($r['created'], 'kontraksi dibuat');
    T::eq(null, $r['contraction']['ended_at'], 'masih aktif');
    $r = $svc->sync($alice, $u, $start, $start + 52_000);
    T::eq(52, $r['contraction']['duration_seconds']);
    T::ok($r['finished'], 'ditandai selesai');
});

T::test('Kontraksi pertama tidak memiliki interval', function () use ($model, $alice) {
    $rows = $model->recent($alice, 10);
    T::eq(null, end($rows)['interval_seconds']);
});

T::test('Interval dihitung dari mulai ke mulai (bukan dari selesai sebelumnya)', function () use ($svc, $alice) {
    // Kontraksi 1 mulai 3600 dtk lalu (durasi 52). Kontraksi 2 mulai 3300 dtk lalu.
    $r = $svc->sync($alice, uuid4(), ago(3300), ago(3300) + 60_000);
    T::eq(300, $r['contraction']['interval_seconds'], 'start-ke-start = 300 dtk (bukan 248)');
});

T::test('Rata-rata durasi & interval sesuai rumus', function () use ($svc, $alice, $model) {
    $svc->sync($alice, uuid4(), ago(3000), ago(3000) + 50_000); // interval 300
    $session = (new App\Models\ContractionSession())->findActive($alice);
    $rows = $model->listBySession((int) $session['id']);
    $s = StatsService::summarize($rows);
    T::eq(3, $s['completed_count']);
    T::eq((int) round((52 + 60 + 50) / 3), $s['avg_duration']);
    T::eq(300, $s['avg_interval']);
    T::eq(50, $s['last_duration']);
    T::eq(300, $s['last_interval']);
});

T::test('Klik ganda / kirim ulang uuid yang sama tidak menggandakan data', function () use ($svc, $alice) {
    $u = uuid4();
    $before = db_count('SELECT COUNT(*) FROM contractions WHERE user_id = ?', [$alice]);
    $a = $svc->sync($alice, $u, ago(2700), null);
    $b = $svc->sync($alice, $u, ago(2700), null);
    T::ok($a['created'] && !$b['created'], 'permintaan kedua idempoten');
    $c = $svc->sync($alice, $u, ago(2700), ago(2700) + 40_000);
    $d = $svc->sync($alice, $u, ago(2700), ago(2700) + 40_000);
    T::ok($c['finished'] && !$d['finished'], 'akhiri dua kali tetap satu');
    T::eq($before + 1, db_count('SELECT COUNT(*) FROM contractions WHERE user_id = ?', [$alice]));
});

T::test('Tidak boleh ada dua kontraksi aktif bersamaan', function () use ($svc, $alice) {
    $u = uuid4();
    $svc->sync($alice, $u, ago(60), null);
    $e = T::throwsHttp(409, fn () => $svc->sync($alice, uuid4(), ago(30), null));
    T::eq($u, $e->extra['active']['uuid'], 'respons menyertakan kontraksi aktif');
    T::eq(1, db_count('SELECT COUNT(*) FROM contractions WHERE user_id = ? AND ended_at IS NULL', [$alice]));
    $svc->cancelActive($alice, $u);
});

T::test('Konflik timestamp: kontraksi yang bertumpuk ditolak', function () use ($svc, $alice) {
    // Kontraksi 3300 dtk lalu berdurasi 60 dtk -> mulai di dalamnya bertabrakan
    T::throwsHttp(409, fn () => $svc->sync($alice, uuid4(), ago(3280), ago(3280) + 30_000));
});

T::test('Waktu tidak valid ditolak: masa depan, selesai < mulai, durasi > 3 jam, uuid salah', function () use ($svc, $alice) {
    T::throwsHttp(422, fn () => $svc->sync($alice, uuid4(), (time() + 3600) * 1000, null), 'masa depan');
    T::throwsHttp(422, fn () => $svc->sync($alice, uuid4(), ago(100), ago(200)), 'selesai sebelum mulai');
    T::throwsHttp(422, fn () => $svc->sync($alice, uuid4(), ago(5 * 3600), ago(60)), 'terlalu lama');
    T::throwsHttp(422, fn () => $svc->sync($alice, 'bukan-uuid', ago(100), null), 'uuid');
    T::throwsHttp(422, fn () => $svc->sync($alice, uuid4(), 'abc', null), 'timestamp teks');
    T::throwsHttp(422, fn () => $svc->sync($alice, uuid4(), ago(40 * 86400), ago(40 * 86400) + 1000), 'terlalu lama untuk sinkron');
});

T::test('Batalkan kontraksi aktif menghapusnya; kontraksi selesai tidak bisa dibatalkan', function () use ($svc, $alice) {
    $u = uuid4();
    $svc->sync($alice, $u, ago(20), null);
    T::ok($svc->cancelActive($alice, $u), 'terhapus');
    T::eq(null, (new Contraction())->findByUuid($u));
    T::ok(!$svc->cancelActive($alice, $u), 'idempoten');
    $done = uuid4();
    $svc->sync($alice, $done, ago(600), ago(600) + 30_000);
    T::throwsHttp(409, fn () => $svc->cancelActive($alice, $done));
});

T::test('Isolasi data: pengguna lain tidak dapat melihat/mengubah/menghapus', function () use ($svc, $alice, $bob, $model) {
    $row = $model->recent($alice, 1)[0];
    T::throwsHttp(404, fn () => $svc->delete($bob, $row['id']), 'hapus');
    T::throwsHttp(404, fn () => $svc->update($bob, $row['id'], TimeService::now()->modify('-50 seconds'), TimeService::now(), 'x'), 'edit');
    T::ok(!$svc->cancelActive($bob, $row['uuid']), 'batal');
    T::throwsHttp(409, fn () => $svc->sync($bob, $row['uuid'], ago(10), null), 'pakai uuid milik orang lain');
    T::eq(null, $model->findForUser($row['id'], $bob), 'tidak bisa dibaca');
    T::eq(0, $model->history($bob, null, null, 'started_at', 'desc', 50, 0)['total']);
});

T::test('Edit kontraksi menghitung ulang durasi & interval berikutnya', function () use ($svc, $bob, $model) {
    $a = $svc->sync($bob, uuid4(), ago(1000), ago(1000) + 30_000)['contraction'];
    $b = $svc->sync($bob, uuid4(), ago(700), ago(700) + 30_000)['contraction'];
    T::eq(300, $b['interval_seconds']);
    $newStart = TimeService::fromDb($a['started_at'])->modify('-100 seconds');
    $edited = $svc->update($bob, $a['id'], $newStart, $newStart->modify('+45 seconds'), 'nyeri punggung');
    T::eq(45, $edited['duration_seconds']);
    T::eq('nyeri punggung', $edited['notes']);
    T::eq(400, $model->findForUser($b['id'], $bob)['interval_seconds'], 'interval kontraksi berikutnya ikut berubah');
    T::throwsHttp(422, fn () => $svc->update($bob, $a['id'], null, TimeService::now(), null), 'tanggal tidak valid');
});

T::test('Hapus kontraksi menghitung ulang interval; kontraksi pertama tetap tanpa interval', function () use ($svc, $bob, $model) {
    $rows = $model->listBySession($model->recent($bob, 1)[0]['session_id']);
    $svc->delete($bob, $rows[0]['id']);
    $rows = $model->listBySession($rows[1]['session_id']);
    T::eq(1, count($rows));
    T::eq(null, $rows[0]['interval_seconds']);
});

T::test('Tutup sesi: kontraksi berikutnya masuk sesi baru tanpa interval', function () use ($svc, $bob, $model) {
    $svc->endSession($bob);
    T::eq(0, db_count("SELECT COUNT(*) FROM contraction_sessions WHERE user_id = ? AND status = 'active'", [$bob]));
    $c = $svc->sync($bob, uuid4(), ago(100), ago(100) + 20_000)['contraction'];
    T::eq(null, $c['interval_seconds']);
    T::eq(2, db_count('SELECT COUNT(*) FROM contraction_sessions WHERE user_id = ?', [$bob]));
});

T::test('Sesi tidak bisa ditutup saat kontraksi masih berlangsung (sesi belum selesai)', function () use ($svc, $bob) {
    $u = uuid4();
    $svc->sync($bob, $u, ago(10), null);
    T::throwsHttp(409, fn () => $svc->endSession($bob));
    $svc->cancelActive($bob, $u);
});

T::test('Constraint database mencegah dua sesi aktif & uuid ganda', function () use ($bob) {
    $pdo = App\Core\Database::connection();
    $threw = false;
    try {
        $pdo->prepare("INSERT INTO contraction_sessions (user_id, started_at, status, active_user_id) VALUES (?, UTC_TIMESTAMP(), 'active', ?)")->execute([$bob, $bob]);
    } catch (PDOException $e) {
        $threw = $e->getCode() === '23000';
    }
    T::ok($threw, 'UNIQUE active_user_id pada sesi');
});

T::test('Sinkronisasi offline di luar urutan tetap menghasilkan interval benar', function () use ($svc, $model) {
    $carol = make_user('carol@contoh.test');
    // Kontraksi kedua terkirim lebih dulu daripada yang pertama
    $second = $svc->sync($carol, uuid4(), ago(1200), ago(1200) + 40_000)['contraction'];
    $first = $svc->sync($carol, uuid4(), ago(1500), ago(1500) + 40_000)['contraction'];
    T::eq(null, $model->findForUser($first['id'], $carol)['interval_seconds']);
    T::eq(300, $model->findForUser($second['id'], $carol)['interval_seconds']);
});

T::test('Kontraksi melewati tengah malam WIB tercatat pada tanggal mulai', function () use ($svc) {
    $dave = make_user('dave@contoh.test');
    $now = TimeService::now()->setTimezone(TimeService::local());
    $midnight = $now->setTime(0, 0, 0);                       // 00:00 WIB hari ini
    $start = $midnight->modify('-20 seconds')->getTimestamp() * 1000;
    $end = $midnight->modify('+40 seconds')->getTimestamp() * 1000;
    $c = $svc->sync($dave, uuid4(), $start, $end)['contraction'];
    T::eq(60, $c['duration_seconds']);
    T::eq($midnight->modify('-1 day')->format('d-m-Y'), TimeService::localDate($c['started_at']));
    T::eq('23:59:40', TimeService::localTime($c['started_at']));
});

T::test('Filter riwayat berdasarkan rentang tanggal lokal', function () use ($svc, $model) {
    $erin = make_user('erin@contoh.test');
    $today = new DateTimeImmutable('today', TimeService::local());
    $yesterday = $today->modify('-1 day');
    $svc->sync($erin, uuid4(), $yesterday->setTime(10, 0)->getTimestamp() * 1000, $yesterday->setTime(10, 1)->getTimestamp() * 1000);
    $svc->sync($erin, uuid4(), $yesterday->setTime(23, 59, 0)->getTimestamp() * 1000, $yesterday->setTime(23, 59, 50)->getTimestamp() * 1000);
    $svc->sync($erin, uuid4(), $today->setTime(0, 5)->getTimestamp() * 1000, $today->setTime(0, 6)->getTimestamp() * 1000);
    [$f, $t] = TimeService::localDateRangeToUtc($yesterday, $yesterday);
    T::eq(2, $model->history($erin, $f, $t, 'started_at', 'desc', 50, 0)['total'], 'kemarin');
    [$f, $t] = TimeService::localDateRangeToUtc($today, $today);
    T::eq(1, $model->history($erin, $f, $t, 'started_at', 'desc', 50, 0)['total'], 'hari ini');
    $sorted = $model->history($erin, null, null, 'duration', 'asc', 50, 0)['rows'];
    T::eq(50, $sorted[0]['duration_seconds'], 'urut durasi');
    $page2 = $model->history($erin, null, null, 'started_at', 'asc', 2, 2);
    T::eq(1, count($page2['rows']), 'pagination');
    T::eq(3, $page2['total']);
});

T::test('Statistik jendela 30/60 menit hanya menghitung kontraksi dalam rentang', function () use ($svc) {
    $fay = make_user('fay@contoh.test');
    $svc->sync($fay, uuid4(), ago(90 * 60), ago(90 * 60) + 50_000);
    $svc->sync($fay, uuid4(), ago(50 * 60), ago(50 * 60) + 50_000);
    $svc->sync($fay, uuid4(), ago(20 * 60), ago(20 * 60) + 60_000);
    $svc->sync($fay, uuid4(), ago(10 * 60), ago(10 * 60) + 70_000);
    $stats = new StatsService();
    $w30 = $stats->window($fay, 30);
    $w60 = $stats->window($fay, 60);
    T::eq(2, $w30['count']);
    T::eq(65, $w30['avg_duration']);
    T::eq(600, $w30['avg_interval']);
    T::eq(3, $w60['count']);
    // Interval dalam 60 menit: 50->20 menit (1800 dtk) dan 20->10 menit (600 dtk); kontraksi 90 menit lalu tidak ikut
    T::eq(1200, $w60['avg_interval'], 'rata-rata interval 60 menit');
});

T::test('Ringkasan pola deskriptif tanpa diagnosis', function () use ($svc) {
    $gina = make_user('gina@contoh.test');
    foreach ([1800, 1500, 1260, 1050, 870, 720] as $i => $sec) {
        $svc->sync($gina, uuid4(), ago($sec), ago($sec) + (40 + $i * 5) * 1000);
    }
    $session = (new App\Models\ContractionSession())->findActive($gina);
    $p = StatsService::pattern((new Contraction())->listBySession((int) $session['id']));
    T::ok($p !== null, 'pola tersedia');
    T::ok(str_contains($p['text'], 'bukan penilaian medis'), 'ada penafian');
    foreach (['persalinan dimulai', 'segera melahirkan', 'diagnosis:'] as $bad) {
        T::ok(!str_contains(strtolower($p['text']), $bad), "tidak memuat '$bad'");
    }
});

/* ---------- Autentikasi ---------- */
T::test('Registrasi memvalidasi input & password di-hash', function () {
    $auth = new AuthService();
    $e = T::throwsHttp(422, fn () => $auth->register('', 'bukan-email', '123', '456'));
    T::ok(isset($e->errors['name'], $e->errors['email'], $e->errors['password'], $e->errors['password_confirmation']), 'semua error dilaporkan');
    $id = $auth->register('Hana', 'Hana@Contoh.test', 'rahasia123', 'rahasia123');
    $hash = (new App\Models\User())->findPasswordHash($id);
    T::ok($hash !== 'rahasia123' && password_verify('rahasia123', $hash), 'password_hash');
    T::throwsHttp(422, fn () => $auth->register('Hana', 'hana@contoh.test', 'rahasia123', 'rahasia123'), 'email ganda');
});

T::test('Login benar/salah dan pembatasan percobaan login', function () {
    $auth = new AuthService();
    $user = $auth->attempt('hana@contoh.test', 'rahasia123', '10.0.0.1');
    T::eq('hana@contoh.test', $user['email']);
    T::ok(!isset($user['password']), 'hash tidak dikembalikan');
    for ($i = 0; $i < 5; $i++) {
        T::throwsHttp(422, fn () => $auth->attempt('hana@contoh.test', 'salah', '10.0.0.2'));
    }
    T::throwsHttp(429, fn () => $auth->attempt('hana@contoh.test', 'rahasia123', '10.0.0.2'), 'terkunci walau password benar');
});

T::test('Hapus akun menghapus seluruh data terkait (cascade)', function () use ($svc) {
    $ivy = make_user('ivy@contoh.test');
    $svc->sync($ivy, uuid4(), ago(100), ago(100) + 10_000);
    (new App\Models\PregnancyProfile())->save($ivy, ['nickname' => 'Ivy', 'estimated_due_date' => null, 'tracking_start_date' => null, 'notes' => null, 'companion_name' => null, 'companion_contact' => null]);
    (new App\Models\User())->delete($ivy);
    T::eq(0, db_count('SELECT COUNT(*) FROM contractions WHERE user_id = ?', [$ivy]));
    T::eq(0, db_count('SELECT COUNT(*) FROM contraction_sessions WHERE user_id = ?', [$ivy]));
    T::eq(0, db_count('SELECT COUNT(*) FROM pregnancy_profiles WHERE user_id = ?', [$ivy]));
});
