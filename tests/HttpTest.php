<?php

declare(strict_types=1);

/*
 * Uji integrasi HTTP: menjalankan server bawaan PHP terhadap database uji
 * lalu menguji alur nyata (login, CSRF, API timer, riwayat, CSV, isolasi data).
 */

echo "\n\033[1mIntegrasi HTTP\033[0m\n";

reset_test_database();

final class Http
{
    private string $jar;
    public ?string $csrf = null;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'kkjar');
    }

    public function request(string $method, string $path, array|string|null $body = null, array $headers = [], bool $json = false): array
    {
        $ch = curl_init($this->base . $path);
        $this->configure($ch, $method, $body, $headers, $json);
        $raw = curl_exec($ch);
        return $this->parse($ch, (string) $raw);
    }

    public function configure($ch, string $method, array|string|null $body, array $headers, bool $json): void
    {
        $h = array_merge(['Accept: ' . ($json ? 'application/json' : 'text/html')], $headers);
        if ($json && $body !== null) {
            $h[] = 'Content-Type: application/json';
            $body = json_encode($body);
        } elseif (is_array($body)) {
            $body = http_build_query($body);
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_HTTPHEADER     => $h,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }

    public function parse($ch, string $raw): array
    {
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerText = substr($raw, 0, $hsize);
        $body = substr($raw, $hsize);
        $headers = [];
        foreach (explode("\r\n", $headerText) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))][] = trim($v);
            }
        }
        $data = json_decode($body, true);
        return ['status' => $status, 'headers' => $headers, 'body' => $body, 'json' => is_array($data) ? $data : null];
    }

    public function header(array $res, string $name): string
    {
        return implode(', ', $res['headers'][strtolower($name)] ?? []);
    }

    /** Ambil token CSRF dari halaman HTML. */
    public function loadCsrf(string $path): string
    {
        $res = $this->request('GET', $path);
        if (preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $res['body'], $m) || preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $res['body'], $m)) {
            return $this->csrf = $m[1];
        }
        throw new RuntimeException("Token CSRF tidak ditemukan di $path (status {$res['status']})");
    }

    public function login(string $email, string $password, bool $remember = false): array
    {
        $this->loadCsrf('/login');
        $form = ['_csrf' => $this->csrf, 'email' => $email, 'password' => $password];
        if ($remember) {
            $form['remember'] = '1';
        }
        $res = $this->request('POST', '/login', $form);
        if ($res['status'] === 303) {
            // Ikuti pengalihan (mis. ke /ganti-password bila wajib ganti password)
            $home = $this->request('GET', '/');
            $this->loadCsrf($home['status'] === 303 ? (string) parse_url($this->header($home, 'location'), PHP_URL_PATH) : '/');
        }
        return $res;
    }

    public function api(string $method, string $path, ?array $body = null): array
    {
        return $this->request($method, $path, $body ?? ($method === 'POST' ? [] : null), $method === 'POST' ? ['X-CSRF-Token: ' . $this->csrf] : [], true);
    }

    public function jar(): string
    {
        return $this->jar;
    }

    /** Hapus cookie dari jar (mensimulasikan browser ditutup / session kedaluwarsa). */
    public function dropCookie(string $name): void
    {
        $lines = file($this->jar) ?: [];
        $keep = array_filter($lines, static fn ($l) => !str_contains($l, "\t$name\t"));
        file_put_contents($this->jar, implode('', $keep));
    }

    public function cookie(array $res, string $name): ?string
    {
        foreach ($res['headers']['set-cookie'] ?? [] as $c) {
            if (str_starts_with($c, "$name=")) {
                return $c;
            }
        }
        return null;
    }
}

// Jalankan server
$port = 8765 + random_int(0, 200);
$base = "http://127.0.0.1:$port";
$cmd = sprintf('PHP_CLI_SERVER_WORKERS=6 KK_ENV_FILE=%s exec php -S 127.0.0.1:%d -t %s %s', escapeshellarg(BASE_PATH . '/.env.testing'), $port, escapeshellarg(BASE_PATH . '/public'), escapeshellarg(BASE_PATH . '/public/router.php'));
$server = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(static function () use ($server) {
    proc_terminate($server);
});
for ($i = 0; $i < 50; $i++) {
    if (@fsockopen('127.0.0.1', $port)) {
        break;
    }
    usleep(100_000);
}

$guest = new Http($base);
$a = new Http($base);
$b = new Http($base);

T::test('Tamu diarahkan ke login; API mengembalikan 401 JSON', function () use ($guest) {
    $r = $guest->request('GET', '/');
    T::eq(303, $r['status']);
    T::ok(str_ends_with($guest->header($r, 'location'), '/login'), 'redirect ke /login');
    $r = $guest->request('GET', '/api/state', null, [], true);
    T::eq(401, $r['status']);
    T::eq(false, $r['json']['ok']);
});

T::test('Form tanpa token CSRF ditolak (419)', function () use ($guest) {
    $r = $guest->request('POST', '/login', ['email' => 'x@y.z', 'password' => 'a']);
    T::eq(419, $r['status']);
    $r = $guest->request('POST', '/api/contractions/sync', ['uuid' => uuid4()], [], true);
    T::ok(in_array($r['status'], [401, 419], true), 'API POST tanpa login/CSRF ditolak');
});

T::test('Registrasi pengguna (validasi server) lalu login', function () use ($a, $b) {
    $a->loadCsrf('/daftar');
    $r = $a->request('POST', '/daftar', ['_csrf' => $a->csrf, 'name' => 'Ayu', 'email' => 'salah', 'password' => '123', 'password_confirmation' => '123']);
    T::eq(422, $r['status'], 'input tidak valid');
    T::ok(str_contains($r['body'], 'Format email tidak valid'), 'pesan validasi tampil');
    $r = $a->request('POST', '/daftar', ['_csrf' => $a->csrf, 'name' => 'Ayu', 'email' => 'ayu@contoh.test', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123']);
    T::eq(303, $r['status']);
    $b->loadCsrf('/daftar');
    $b->request('POST', '/daftar', ['_csrf' => $b->csrf, 'name' => 'Bunga', 'email' => 'bunga@contoh.test', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123']);
});

T::test('Login gagal menampilkan pesan, login berhasil membuat session aman', function () use ($a, $b) {
    $r = $a->login('ayu@contoh.test', 'salahpassword');
    T::eq(422, $r['status']);
    T::ok(str_contains($r['body'], 'Email atau password salah'), 'pesan gagal');
    $r = $a->login('ayu@contoh.test', 'rahasia123');
    T::eq(303, $r['status']);
    $cookie = $a->header($r, 'set-cookie');
    T::ok(stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'SameSite=Lax') !== false, 'cookie HttpOnly & SameSite');
    T::eq(303, $b->login('bunga@contoh.test', 'rahasia123')['status']);
});

T::test('Halaman berisi data pribadi tidak boleh di-cache & memakai header keamanan', function () use ($a) {
    $r = $a->request('GET', '/');
    T::eq(200, $r['status']);
    T::ok(str_contains($a->header($r, 'cache-control'), 'no-store'), 'Cache-Control no-store');
    T::ok(str_contains($a->header($r, 'content-security-policy'), "default-src 'self'"), 'CSP');
    T::eq('DENY', $a->header($r, 'x-frame-options'));
    $api = $a->api('GET', '/api/state');
    T::ok(str_contains($a->header($api, 'cache-control'), 'no-store'), 'API no-store');
});

$startedMs = ago(600);
$uuidA = uuid4();

T::test('Timer: mulai (201), kirim ulang uuid sama tidak duplikat (200)', function () use ($a, $startedMs, $uuidA) {
    $r = $a->api('POST', '/api/contractions/sync', ['uuid' => $uuidA, 'started_at' => $startedMs, 'ended_at' => null]);
    T::eq(201, $r['status']);
    T::eq(true, $r['json']['saved']);
    T::eq($uuidA, $r['json']['state']['active']['uuid']);
    $r = $a->api('POST', '/api/contractions/sync', ['uuid' => $uuidA, 'started_at' => $startedMs, 'ended_at' => null]);
    T::eq(200, $r['status']);
    T::eq(false, $r['json']['created']);
});

T::test('Refresh halaman: kontraksi aktif dipulihkan dari server dengan waktu mulai yang sama', function () use ($a, $startedMs, $uuidA) {
    $r = $a->api('GET', '/api/state');
    T::eq($uuidA, $r['json']['active']['uuid']);
    T::eq(intdiv($startedMs, 1000) * 1000, $r['json']['active']['started_at_ms']);
    $page = $a->request('GET', '/');
    T::ok(str_contains($page['body'], $uuidA), 'state awal halaman memuat kontraksi aktif');
});

T::test('Mulai kontraksi lain saat masih aktif ditolak (409)', function () use ($a) {
    $r = $a->api('POST', '/api/contractions/sync', ['uuid' => uuid4(), 'started_at' => ago(5), 'ended_at' => null]);
    T::eq(409, $r['status']);
});

T::test('Timer: akhiri menghitung durasi & mengosongkan kontraksi aktif', function () use ($a, $startedMs, $uuidA) {
    $r = $a->api('POST', '/api/contractions/sync', ['uuid' => $uuidA, 'started_at' => $startedMs, 'ended_at' => $startedMs + 65_000]);
    T::eq(200, $r['status']);
    T::eq(65, $r['json']['contraction']['duration_seconds']);
    T::eq('01:05', $r['json']['contraction']['duration_label']);
    T::eq(null, $r['json']['state']['active']);
});

T::test('Klik ganda paralel (5 permintaan bersamaan, uuid sama) hanya membuat 1 data', function () use ($a, $base) {
    $u = uuid4();
    $mh = curl_multi_init();
    $handles = [];
    for ($i = 0; $i < 5; $i++) {
        $ch = curl_init($base . '/api/contractions/sync');
        $a->configure($ch, 'POST', ['uuid' => $u, 'started_at' => ago(300), 'ended_at' => ago(300) + 30_000], ['X-CSRF-Token: ' . $a->csrf], true);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);
    $statuses = array_map(fn ($ch) => $a->parse($ch, (string) curl_multi_getcontent($ch))['status'], $handles);
    sort($statuses);
    T::eq([200, 200, 200, 200, 201], $statuses, 'satu dibuat, sisanya idempoten');
    T::eq(1, db_count('SELECT COUNT(*) FROM contractions WHERE uuid = ?', [$u]));
});

T::test('Mulai paralel dengan uuid berbeda tetap hanya 1 kontraksi aktif', function () use ($a, $base) {
    $mh = curl_multi_init();
    $handles = [];
    for ($i = 0; $i < 4; $i++) {
        $ch = curl_init($base . '/api/contractions/sync');
        $a->configure($ch, 'POST', ['uuid' => uuid4(), 'started_at' => ago(20) + $i, 'ended_at' => null], ['X-CSRF-Token: ' . $a->csrf], true);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);
    $statuses = array_map(fn ($ch) => $a->parse($ch, (string) curl_multi_getcontent($ch))['status'], $handles);
    sort($statuses);
    T::eq([201, 409, 409, 409], $statuses);
    $active = $a->api('GET', '/api/state')['json']['active'];
    $r = $a->api('POST', '/api/contractions/cancel', ['uuid' => $active['uuid']]);
    T::eq(true, $r['json']['deleted'], 'dibatalkan');
});

T::test('Catatan berisi HTML disanitasi (XSS)', function () use ($a) {
    $r = $a->api('POST', '/api/contractions/sync', ['uuid' => uuid4(), 'started_at' => ago(200), 'ended_at' => ago(200) + 20_000, 'notes' => '<script>alert(1)</script>Kram']);
    T::eq(201, $r['status']);
    T::eq('alert(1)Kram', $r['json']['contraction']['notes']);
    $page = $a->request('GET', '/riwayat');
    T::ok(!str_contains($page['body'], '<script>alert'), 'tidak ada script tersisip');
});

T::test('Edit lewat API: input tanggal tidak valid ditolak (422), valid diterima', function () use ($a) {
    $row = $a->api('GET', '/api/state')['json']['recent'][0];
    $r = $a->api('POST', '/api/contractions/update', ['id' => $row['id'], 'start_date' => '2026-02-30', 'start_time' => '10:00:00', 'end_date' => '2026-02-30', 'end_time' => '10:01:00']);
    T::eq(422, $r['status']);
    $r = $a->api('POST', '/api/contractions/update', ['id' => $row['id'], 'start_date' => $row['date_iso'], 'start_time' => $row['start_time'], 'end_date' => $row['end_date_iso'], 'end_time' => $row['end_time'], 'notes' => 'Diedit']);
    T::eq(200, $r['status']);
    T::eq('Diedit', $r['json']['contraction']['notes']);
});

T::test('Pengguna lain tidak dapat mengakses data (edit/hapus/batal/riwayat/CSV)', function () use ($a, $b) {
    $row = $a->api('GET', '/api/state')['json']['recent'][0];
    $r = $b->api('POST', '/api/contractions/delete', ['id' => $row['id']]);
    T::eq(404, $r['status'], 'hapus');
    $r = $b->api('POST', '/api/contractions/update', ['id' => $row['id'], 'start_date' => $row['date_iso'], 'start_time' => '00:00:00', 'end_date' => $row['date_iso'], 'end_time' => '00:00:30']);
    T::eq(404, $r['status'], 'edit');
    T::eq([], $b->api('GET', '/api/state')['json']['recent'], 'state pengguna B kosong');
    T::ok(!str_contains($b->request('GET', '/riwayat')['body'], 'Diedit'), 'riwayat B tidak memuat data A');
    T::ok(!str_contains($b->request('GET', '/ekspor-csv')['body'], 'Diedit'), 'CSV B tidak memuat data A');
    T::eq(1, db_count('SELECT COUNT(*) FROM contractions WHERE id = ?', [$row['id']]), 'data A utuh');
});

T::test('Filter riwayat: tanggal tidak valid ditolak (422), rentang valid berfungsi', function () use ($a) {
    $r = $a->request('GET', '/riwayat?dari=2026-02-30');
    T::eq(422, $r['status']);
    T::ok(str_contains($r['body'], 'Tanggal mulai tidak valid'), 'pesan validasi');
    $today = (new DateTimeImmutable('now', App\Services\TimeService::local()))->format('Y-m-d');
    $r = $a->request('GET', '/riwayat?tanggal=' . $today);
    T::eq(200, $r['status']);
    T::ok(preg_match('/data-testid="stat-total">(\d+)</', $r['body'], $m) === 1 && (int) $m[1] >= 3, 'ada data hari ini');
    $r = $a->request('GET', '/riwayat?dari=2020-01-01&sampai=2020-01-31');
    T::ok(str_contains($r['body'], 'data-testid="history-empty"'), 'rentang lama kosong');
    $r = $a->request('GET', '/riwayat?dari=2026-10-10&sampai=2026-10-01');
    T::eq(422, $r['status'], 'tanggal akhir sebelum tanggal mulai');
});

T::test('Ekspor CSV: header, BOM, baris data, dan perlindungan formula', function () use ($a) {
    $a->api('POST', '/api/contractions/sync', ['uuid' => uuid4(), 'started_at' => ago(120), 'ended_at' => ago(120) + 15_000, 'notes' => '=HYPERLINK("x")']);
    $r = $a->request('GET', '/ekspor-csv');
    T::eq(200, $r['status']);
    T::ok(str_contains($a->header($r, 'content-type'), 'text/csv'), 'content-type');
    T::ok(str_contains($a->header($r, 'content-disposition'), 'attachment; filename="riwayat-kontraksi-'), 'nama file');
    T::ok(str_starts_with($r['body'], "\xEF\xBB\xBF"), 'BOM UTF-8');
    $lines = array_values(array_filter(explode("\n", trim(substr($r['body'], 3)))));
    T::eq('No;Tanggal;"Waktu Mulai";"Waktu Selesai";"Durasi (mm:dd)";"Durasi (detik)";"Interval (mm:dd)";"Interval (detik)";Catatan', $lines[0]);
    T::eq(db_count('SELECT COUNT(*) FROM contractions c JOIN users u ON u.id = c.user_id WHERE u.email = ?', ['ayu@contoh.test']), count($lines) - 1, 'jumlah baris');
    T::ok(str_contains($r['body'], "'=HYPERLINK"), 'formula diawali apostrof');
    $r = $a->request('GET', '/ekspor-csv?dari=bukan-tanggal');
    T::eq(422, $r['status']);
});

T::test('Laporan cetak tersedia', function () use ($a) {
    $r = $a->request('GET', '/cetak-laporan');
    T::eq(200, $r['status']);
    T::ok(str_contains($r['body'], 'Laporan Kontraksi') && str_contains($r['body'], 'bukan hasil pemeriksaan'), 'isi laporan');
});

T::test('Profil: HPL tidak valid ditolak, data opsional tersimpan, usia kehamilan tampil', function () use ($a) {
    $csrf = $a->loadCsrf('/profil');
    $r = $a->request('POST', '/profil', ['_csrf' => $csrf, 'name' => 'Ayu', 'estimated_due_date' => '2026-02-31']);
    T::eq(422, $r['status']);
    $due = (new DateTimeImmutable('today', App\Services\TimeService::local()))->modify('+14 days')->format('Y-m-d');
    $r = $a->request('POST', '/profil', ['_csrf' => $csrf, 'name' => 'Ayu', 'nickname' => 'Bunda Ayu', 'estimated_due_date' => $due]);
    T::eq(303, $r['status']);
    $page = $a->request('GET', '/');
    T::ok(str_contains($page['body'], 'Bunda Ayu'), 'nama panggilan');
    T::ok(str_contains($page['body'], '38 minggu 0 hari'), 'usia kehamilan');
    $a->loadCsrf('/');
});

T::test('Halaman grafik, info keselamatan, dan sesi', function () use ($a) {
    $r = $a->request('GET', '/grafik');
    T::eq(200, $r['status']);
    T::ok(str_contains($r['body'], 'tidak membuat diagnosis'), 'penafian grafik');
    $r = $a->request('GET', '/info');
    foreach (['bukan alat diagnosis', 'perdarahan', 'Air ketuban pecah', 'Gerakan janin berkurang', 'Jangan menunggu pola kontraksi tertentu'] as $text) {
        T::ok(stripos($r['body'], $text) !== false, "info memuat '$text'");
    }
    $r = $a->api('POST', '/api/sessions/end');
    T::eq(true, $r['json']['ended']);
});

T::test('Aset PWA: manifest, service worker, ikon, halaman offline', function () use ($guest) {
    $m = $guest->request('GET', '/manifest.json');
    T::eq(200, $m['status']);
    $json = json_decode($m['body'], true);
    T::eq('standalone', $json['display']);
    T::ok(!empty($json['theme_color']) && !empty($json['start_url']), 'theme_color & start_url');
    $sizes = array_column($json['icons'], 'sizes');
    T::ok(in_array('192x192', $sizes, true) && in_array('512x512', $sizes, true), 'ikon 192 & 512');
    foreach (['/service-worker.js', '/offline.html', '/assets/icons/icon-192.png', '/assets/icons/icon-512.png'] as $p) {
        T::eq(200, $guest->request('GET', $p)['status'], $p);
    }
    $sw = $guest->request('GET', '/service-worker.js')['body'];
    T::ok(str_contains($sw, "SCOPE + 'api/'") && str_contains($sw, "req.mode === 'navigate'"), 'SW tidak meng-cache API/halaman');
    T::ok(getimagesize(BASE_PATH . '/public/assets/icons/icon-512.png')[0] === 512, 'ukuran ikon 512');
});

T::test('Logout hanya via POST + CSRF dan mengakhiri session', function () use ($a) {
    T::eq(405, $a->request('GET', '/logout')['status']);
    $r = $a->request('POST', '/logout', ['_csrf' => $a->csrf]);
    T::eq(303, $r['status']);
    T::eq(401, $a->api('GET', '/api/state')['status']);
});

T::test('Login tanpa "ingat saya" tidak membuat cookie token', function () use ($base) {
    $c = new Http($base);
    $r = $c->login('ayu@contoh.test', 'rahasia123');
    T::eq(303, $r['status']);
    T::eq(null, $c->cookie($r, 'KKREMEMBER'));
    $c->dropCookie('KKSESSID');
    T::eq(401, $c->api('GET', '/api/state')['status'], 'tanpa session harus login ulang');
});

T::test('Ingat saya: cookie 30 hari HttpOnly, login pulih saat session hilang, logout mencabut token', function () use ($base) {
    $c = new Http($base);
    $r = $c->login('ayu@contoh.test', 'rahasia123', true);
    T::eq(303, $r['status']);
    $cookie = (string) $c->cookie($r, 'KKREMEMBER');
    T::ok(preg_match('/^KKREMEMBER=([a-f0-9]{24}%3A[a-f0-9]{64});/', $cookie, $m) === 1, 'cookie token dikirim');
    $value = $m[1];
    T::ok(stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'SameSite=Lax') !== false, 'cookie HttpOnly & SameSite');
    T::ok(preg_match('/Max-Age=(\d+)/', $cookie, $age) === 1 && abs((int) $age[1] - 30 * 86400) < 10, 'berlaku 30 hari');

    $c->dropCookie('KKSESSID'); // browser ditutup / session timeout
    T::eq(200, $c->request('GET', '/')['status'], 'login pulih dari token');
    T::eq(303, $c->request('GET', '/login')['status'], 'halaman login mengarahkan ke dashboard');

    $c->loadCsrf('/');
    $r = $c->request('POST', '/logout', ['_csrf' => $c->csrf]);
    T::ok(str_contains((string) $c->cookie($r, 'KKREMEMBER'), 'KKREMEMBER=deleted'), 'cookie dihapus saat logout');
    $stolen = new Http($base);
    T::eq(401, $stolen->request('GET', '/api/state', null, ['Cookie: KKREMEMBER=' . $value], true)['status'], 'token lama tidak berlaku lagi');
});

T::test('Ganti password: perangkat ini tetap dipercaya, token perangkat lain tidak berlaku', function () use ($base) {
    make_user('dewi@contoh.test');
    $hp = new Http($base);
    $laptop = new Http($base);
    T::eq(303, $hp->login('dewi@contoh.test', 'rahasia123', true)['status']);
    T::eq(303, $laptop->login('dewi@contoh.test', 'rahasia123', true)['status']);
    $hp->loadCsrf('/ganti-password');
    $r = $hp->request('POST', '/ganti-password', ['_csrf' => $hp->csrf, 'current_password' => 'rahasia123', 'password' => 'dewibaru123', 'password_confirmation' => 'dewibaru123']);
    T::eq(303, $r['status']);
    $hp->dropCookie('KKSESSID');
    $laptop->dropCookie('KKSESSID');
    T::eq(200, $hp->api('GET', '/api/state')['status'], 'perangkat yang mengganti password tetap login');
    T::eq(401, $laptop->api('GET', '/api/state')['status'], 'perangkat lain wajib login ulang');
});

T::test('Pembatasan percobaan login (429 setelah 5 kali gagal)', function () use ($base) {
    $c = new Http($base);
    for ($i = 0; $i < 5; $i++) {
        T::eq(422, $c->login('bunga@contoh.test', 'salah-' . $i)['status']);
    }
    T::eq(429, $c->login('bunga@contoh.test', 'rahasia123')['status']);
});

/* ---------------- Admin ---------------- */
echo "\n\033[1mAdmin (HTTP)\033[0m\n";

(new App\Models\User())->create('Admin Satu', 'admin@contoh.test', password_hash('rahasia123', PASSWORD_DEFAULT), 'admin');
$admin = new Http($base);
$member = new Http($base);

/** Ambil flash admin (JSON) dari halaman daftar pengguna. */
function admin_flash(Http $h, string $query = ''): ?array
{
    $page = $h->request('GET', '/admin' . $query);
    if (preg_match('#<script type="application/json" id="adminFlash">(.*?)</script>#s', $page['body'], $m)) {
        return json_decode(html_entity_decode($m[1]), true);
    }
    return null;
}

T::test('Pengguna biasa tidak dapat membuka halaman/aksi admin (403)', function () use ($member) {
    T::eq(303, $member->login('ayu@contoh.test', 'rahasia123')['status']);
    T::eq(403, $member->request('GET', '/admin')['status']);
    $r = $member->request('POST', '/admin/pengguna/reset-password', ['_csrf' => $member->csrf, 'id' => 1]);
    T::eq(403, $r['status']);
    T::ok(!str_contains($member->request('GET', '/')['body'], 'Manajemen Pengguna'), 'menu admin tersembunyi');
});

T::test('Admin melihat daftar pengguna tanpa data kesehatan', function () use ($admin) {
    T::eq(303, $admin->login('admin@contoh.test', 'rahasia123')['status']);
    $r = $admin->request('GET', '/admin');
    T::eq(200, $r['status']);
    T::ok(str_contains($r['body'], 'ayu@contoh.test') && str_contains($r['body'], 'bunga@contoh.test'), 'daftar pengguna');
    T::ok(!str_contains($r['body'], 'Diedit') && !str_contains($r['body'], 'Kram'), 'tidak ada catatan kontraksi');
    T::ok(str_contains($admin->request('GET', '/admin?q=bunga')['body'], 'bunga@contoh.test'), 'pencarian');
    T::ok(!str_contains($admin->request('GET', '/admin?q=bunga')['body'], 'ayu@contoh.test'), 'pencarian menyaring');
    T::ok(str_contains($admin->request('GET', '/')['body'], 'Manajemen Pengguna'), 'menu admin tampil');
});

T::test('Aksi admin wajib token CSRF dan menolak open redirect', function () use ($admin) {
    T::eq(419, $admin->request('POST', '/admin/pengguna/status', ['id' => 2, 'active' => '0'])['status']);
    $admin->loadCsrf('/admin');
    $r = $admin->request('POST', '/admin/pengguna/ubah', ['_csrf' => $admin->csrf, 'id' => 99999, 'name' => 'x', 'email' => 'x@x.id', 'role' => 'user', '_back' => '//evil.example/x']);
    T::eq(303, $r['status']);
    T::ok(str_ends_with($admin->header($r, 'location'), '/admin'), 'redirect tetap ke /admin');
    T::eq('error', admin_flash($admin)['type'], 'pesan gagal (tidak ditemukan)');
});

$tempPassword = null;
T::test('Admin menambah pengguna; password sementara tampil sekali', function () use ($admin, &$tempPassword) {
    $admin->loadCsrf('/admin');
    $r = $admin->request('POST', '/admin/pengguna/tambah', ['_csrf' => $admin->csrf, 'name' => 'Citra', 'email' => 'citra@contoh.test', 'role' => 'user', 'password' => '']);
    T::eq(303, $r['status']);
    $flash = admin_flash($admin);
    T::eq('success', $flash['type']);
    T::ok(is_string($flash['password']) && strlen($flash['password']) === 14, 'password sementara');
    $tempPassword = $flash['password'];
    T::eq(null, admin_flash($admin), 'tidak ditampilkan lagi');
});

T::test('Login dengan password sementara dipaksa ganti password dulu', function () use ($base, &$tempPassword) {
    $c = new Http($base);
    $c->loadCsrf('/login');
    $r = $c->request('POST', '/login', ['_csrf' => $c->csrf, 'email' => 'citra@contoh.test', 'password' => $tempPassword]);
    T::eq(303, $r['status']);
    $r = $c->request('GET', '/');
    T::eq(303, $r['status']);
    T::ok(str_ends_with($c->header($r, 'location'), '/ganti-password'), 'diarahkan ke ganti password');
    T::eq(303, $c->request('GET', '/riwayat')['status']);
    T::eq(403, $c->request('GET', '/api/state', null, [], true)['status'], 'API ditolak');
    $page = $c->request('GET', '/ganti-password');
    T::ok(str_contains($page['body'], 'data-testid="must-change"'), 'pesan wajib ganti');
    $c->loadCsrf('/ganti-password');
    $r = $c->request('POST', '/ganti-password', ['_csrf' => $c->csrf, 'current_password' => $tempPassword, 'password' => 'citrabaru123', 'password_confirmation' => 'citrabaru123']);
    T::eq(303, $r['status']);
    T::eq(200, $c->request('GET', '/')['status'], 'akses normal setelah ganti');
});

T::test('Reset password oleh admin langsung mengakhiri sesi pengguna yang sedang login', function () use ($admin, $member) {
    T::eq(200, $member->api('GET', '/api/state')['status'], 'awalnya login');
    $id = db_count("SELECT id FROM users WHERE email = 'ayu@contoh.test'");
    $admin->loadCsrf('/admin');
    $admin->request('POST', '/admin/pengguna/reset-password', ['_csrf' => $admin->csrf, 'id' => $id]);
    $flash = admin_flash($admin);
    T::eq('Password direset', $flash['title']);
    T::eq(401, $member->api('GET', '/api/state')['status'], 'sesi lama berakhir');
    T::eq(422, $member->login('ayu@contoh.test', 'rahasia123')['status'], 'password lama ditolak');
    T::eq(303, $member->login('ayu@contoh.test', $flash['password'])['status'], 'password sementara diterima');
});

T::test('Nonaktifkan akun memutus sesi & menolak login; aktifkan kembali', function () use ($admin, $base) {
    $c = new Http($base);
    T::eq(303, $c->login('citra@contoh.test', 'citrabaru123')['status']);
    $id = db_count("SELECT id FROM users WHERE email = 'citra@contoh.test'");
    $admin->loadCsrf('/admin');
    $admin->request('POST', '/admin/pengguna/status', ['_csrf' => $admin->csrf, 'id' => $id, 'active' => '0']);
    T::eq(401, $c->api('GET', '/api/state')['status'], 'sesi berakhir');
    $r = $c->login('citra@contoh.test', 'citrabaru123');
    T::ok(str_contains($r['body'], 'dinonaktifkan'), 'pesan nonaktif');
    $admin->request('POST', '/admin/pengguna/status', ['_csrf' => $admin->csrf, 'id' => $id, 'active' => '1']);
    T::eq(303, $c->login('citra@contoh.test', 'citrabaru123')['status']);
});

T::test('Admin tidak dapat menonaktifkan diri sendiri; menghapus pengguna lain berhasil', function () use ($admin) {
    $self = db_count("SELECT id FROM users WHERE email = 'admin@contoh.test'");
    $admin->loadCsrf('/admin');
    $admin->request('POST', '/admin/pengguna/status', ['_csrf' => $admin->csrf, 'id' => $self, 'active' => '0']);
    T::eq('error', admin_flash($admin)['type']);
    T::eq(1, db_count('SELECT is_active FROM users WHERE id = ?', [$self]));
    $id = db_count("SELECT id FROM users WHERE email = 'citra@contoh.test'");
    $admin->loadCsrf('/admin');
    $admin->request('POST', '/admin/pengguna/hapus', ['_csrf' => $admin->csrf, 'id' => $id]);
    T::eq('Pengguna dihapus', admin_flash($admin)['title']);
    T::eq(0, db_count('SELECT COUNT(*) FROM users WHERE id = ?', [$id]));
    T::ok(str_contains($admin->request('GET', '/admin')['body'], 'data-testid="audit-log"'), 'log audit tampil');
});
