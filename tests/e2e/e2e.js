/*
 * Uji end-to-end di browser (Chromium via Playwright).
 * Menjalankan: node tests/e2e/e2e.js   (lihat README bagian Pengujian)
 *
 * Mencakup: login, timer mulai/akhiri, klik ganda, refresh, mode offline + antrean
 * IndexedDB + sinkronisasi tanpa duplikasi, riwayat (filter, edit, hapus, CSV),
 * tampilan responsif, dan PWA (manifest + service worker).
 */
'use strict';

const { chromium, devices } = require('playwright');
const { spawn, execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const net = require('net');

const ROOT = path.resolve(__dirname, '..', '..');
const PORT = 8899;
const BASE = `http://localhost:${PORT}/`;
const SHOTS = path.join(__dirname, 'screenshots');
fs.mkdirSync(SHOTS, { recursive: true });

let passed = 0;
const failures = [];
async function test(name, fn) {
    try {
        await fn();
        passed++;
        console.log(`  \x1b[32m✔\x1b[0m ${name}`);
    } catch (e) {
        failures.push(name);
        console.log(`  \x1b[31m✘ ${name}\x1b[0m\n     → ${e.message.split('\n')[0]}`);
    }
}
function assert(cond, msg) { if (!cond) throw new Error(msg); }
function sql(query) {
    return parseInt(execFileSync('php', [path.join(__dirname, 'query.php'), query], { cwd: ROOT }).toString(), 10);
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
function waitPort(port) {
    return new Promise((resolve, reject) => {
        let tries = 0;
        const attempt = () => {
            const s = net.connect(port, '127.0.0.1', () => { s.end(); resolve(); });
            s.on('error', () => { if (++tries > 50) reject(new Error('server tidak hidup')); else setTimeout(attempt, 100); });
        };
        attempt();
    });
}

(async () => {
    execFileSync('php', [path.join(__dirname, 'setup.php')], { cwd: ROOT, stdio: 'inherit' });
    const server = spawn('php', ['-S', `localhost:${PORT}`, '-t', 'public', 'public/router.php'], {
        cwd: ROOT,
        env: Object.assign({}, process.env, { KK_ENV_FILE: path.join(ROOT, '.env.testing'), PHP_CLI_SERVER_WORKERS: '4' }),
        stdio: 'ignore'
    });
    await waitPort(PORT);

    const browser = await chromium.launch();
    const context = await browser.newContext({ ...devices['Pixel 7'], locale: 'id-ID', timezoneId: 'Asia/Jakarta', acceptDownloads: true });
    const page = await context.newPage();
    const consoleErrors = [];
    page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    page.on('pageerror', (e) => consoleErrors.push('pageerror: ' + e.message));

    const button = page.locator('#timerButton');
    const swalClose = async () => {
        const btn = page.locator('.swal2-container .swal2-cancel:visible, .swal2-container .swal2-confirm:visible').last();
        await btn.click();
        await page.locator('.swal2-container').waitFor({ state: 'detached' });
    };
    const noHorizontalScroll = () => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);

    console.log('\n\x1b[1mEnd-to-end (Chromium, Pixel 7)\x1b[0m');

    await test('Login dan logout bekerja', async () => {
        await page.goto(BASE);
        assert(page.url().endsWith('/login'), 'diarahkan ke login');
        await page.screenshot({ path: path.join(SHOTS, '01-login-mobile.png') });
        await page.fill('#email', 'e2e@contoh.test');
        await page.fill('#password', 'salah');
        await page.click('button[type=submit]');
        await page.locator('[data-testid="login-error"]').waitFor();
        await page.fill('#password', 'rahasia123');
        await page.click('button[type=submit]');
        await page.waitForURL(BASE);
        await page.locator('[data-logout-form] button:visible').first().click();
        await page.waitForURL(/\/login$/);
        await page.fill('#email', 'e2e@contoh.test');
        await page.fill('#password', 'rahasia123');
        await page.click('button[type=submit]');
        await page.waitForURL(BASE);
        assert(await button.isVisible(), 'tombol timer tampil');
    });

    await test('Tampilan mobile: navigasi bawah, tanpa geser horizontal, tombol timer besar', async () => {
        assert(await page.locator('.bottom-nav').isVisible(), 'navigasi bawah tampil');
        assert(!(await page.locator('.app-sidebar').isVisible()), 'sidebar tersembunyi di HP');
        assert(await noHorizontalScroll(), 'tidak ada scroll horizontal');
        const box = await button.boundingBox();
        assert(box.width >= 180 && box.height >= 180, `tombol cukup besar (${Math.round(box.width)}px)`);
        await page.screenshot({ path: path.join(SHOTS, '02-dashboard-mobile.png'), fullPage: true });
    });

    await test('Klik ganda pada "Mulai" hanya memulai satu kontraksi', async () => {
        await button.dblclick();
        await page.locator('#timerButton.is-running').waitFor();
        await page.locator('#timerSync:has-text("tersimpan di server")').waitFor({ timeout: 8000 });
        assert(sql('SELECT COUNT(*) FROM contractions') === 1, 'hanya 1 baris');
        assert(sql('SELECT COUNT(*) FROM contractions WHERE ended_at IS NULL') === 1, '1 aktif');
        assert((await button.innerText()).includes('Akhiri Kontraksi'), 'tombol berubah menjadi Akhiri');
    });

    await test('Timer berjalan real-time berdasarkan timestamp', async () => {
        await sleep(2300);
        const t = await page.locator('#timerDisplay').innerText();
        const [m, s] = t.split(':').map(Number);
        assert(m * 60 + s >= 2, `timer bertambah (${t})`);
    });

    await test('Refresh halaman tidak merusak timer aktif', async () => {
        const before = await page.locator('#timerDisplay').innerText();
        await page.reload();
        await page.locator('#timerButton.is-running').waitFor();
        await sleep(300);
        const after = await page.locator('#timerDisplay').innerText();
        const sec = (x) => { const [m, s] = x.split(':').map(Number); return m * 60 + s; };
        assert(sec(after) >= sec(before), `timer berlanjut (${before} -> ${after})`);
        await page.screenshot({ path: path.join(SHOTS, '03-timer-running.png') });
    });

    await test('Akhiri kontraksi: durasi dihitung & ringkasan SweetAlert2 setelah server konfirmasi', async () => {
        await button.click();
        await page.locator('.swal2-popup:has-text("Kontraksi tercatat")').waitFor({ timeout: 8000 });
        const text = await page.locator('.swal2-popup').innerText();
        assert(text.includes('Tersimpan di server'), 'konfirmasi server');
        assert(text.includes('Kontraksi pertama'), 'kontraksi pertama tanpa interval');
        await page.screenshot({ path: path.join(SHOTS, '04-summary.png') });
        await swalClose();
        assert(sql('SELECT COUNT(*) FROM contractions WHERE ended_at IS NOT NULL AND duration_seconds >= 3') === 1, 'durasi tersimpan');
    });

    await test('Kontraksi kedua: interval dari mulai ke mulai tampil di ringkasan', async () => {
        await sleep(1200);
        await button.click();
        await page.locator('#timerSync:has-text("tersimpan di server")').waitFor({ timeout: 8000 });
        await sleep(1100);
        await button.click();
        await page.locator('.swal2-popup:has-text("Kontraksi tercatat")').waitFor({ timeout: 8000 });
        const text = await page.locator('.swal2-popup').innerText();
        assert(!text.includes('Kontraksi pertama'), 'ada interval');
        // Tambah catatan dari dialog ringkasan
        await page.fill('.swal2-textarea', 'Terasa di punggung');
        await page.click('.swal2-confirm');
        await page.locator('.swal2-container').waitFor({ state: 'detached' });
        await page.locator('.swal2-container').waitFor({ state: 'detached' }).catch(() => {});
        await sleep(500);
        assert(sql("SELECT COUNT(*) FROM contractions WHERE notes = 'Terasa di punggung'") === 1, 'catatan tersimpan');
        const stats = await page.locator('[data-stat="count"]').innerText();
        assert(stats === '2', `statistik diperbarui (count=${stats})`);
    });

    await test('Batalkan kontraksi yang salah dengan konfirmasi', async () => {
        await button.click();
        await page.locator('#timerSync:has-text("tersimpan di server")').waitFor({ timeout: 8000 });
        await page.click('#cancelButton');
        await page.locator('.swal2-popup:has-text("Batalkan kontraksi ini?")').waitFor();
        await page.click('.swal2-confirm');
        await page.locator('#timerButton.is-idle').waitFor();
        await sleep(800);
        assert(sql('SELECT COUNT(*) FROM contractions') === 2, 'kontraksi dibatalkan dihapus');
    });

    await test('Service worker aktif & manifest PWA valid', async () => {
        const info = await page.evaluate(async () => {
            const reg = await navigator.serviceWorker.ready;
            const link = document.querySelector('link[rel=manifest]');
            const m = await (await fetch(link.href)).json();
            return { scope: reg.scope, active: !!reg.active, display: m.display, icons: m.icons.map((i) => i.sizes) };
        });
        assert(info.active, 'service worker aktif');
        assert(info.display === 'standalone', 'display standalone');
        assert(info.icons.includes('192x192') && info.icons.includes('512x512'), 'ikon 192 & 512');
        const cached = await page.evaluate(async () => {
            const keys = await caches.keys();
            const all = [];
            for (const k of keys) { const c = await caches.open(k); (await c.keys()).forEach((r) => all.push(new URL(r.url).pathname)); }
            return all;
        });
        assert(cached.includes('/offline.html'), 'offline.html di-cache');
        assert(!cached.some((p) => p.startsWith('/api/') || p === '/' || p === '/login' || p === '/riwayat'), 'halaman pribadi/API tidak di-cache');
    });

    const countBeforeOffline = sql('SELECT COUNT(*) FROM contractions');
    await test('Offline: kontraksi disimpan di perangkat dan TIDAK diklaim tersimpan di server', async () => {
        await context.setOffline(true);
        await page.evaluate(() => window.dispatchEvent(new Event('offline')));
        await page.locator('#connectionBadge:has-text("Offline")').waitFor();
        await button.click();
        await page.locator('#timerSync:has-text("menunggu server")').waitFor();
        await sleep(1500);
        await button.click();
        await page.locator('.swal2-popup:has-text("Tersimpan di perangkat")').waitFor({ timeout: 20000 });
        const text = await page.locator('.swal2-popup').innerText();
        assert(text.includes('Belum tersimpan di server'), 'label belum tersimpan di server');
        assert(!text.includes('Tersimpan di server.'), 'tidak mengklaim tersimpan di server');
        await page.screenshot({ path: path.join(SHOTS, '05-offline-summary.png') });
        await swalClose();
        await page.locator('#pendingBadge:not(.d-none)').waitFor();
        assert(await page.locator('#pendingSection').isVisible(), 'daftar antrean tampil');
        assert(sql('SELECT COUNT(*) FROM contractions') === countBeforeOffline, 'server belum menerima data');
    });

    await test('Offline: memuat ulang menampilkan halaman offline dengan timer & antrean', async () => {
        await page.reload();
        await page.locator('h1:has-text("Mode Offline")').waitFor();
        await page.locator('#pendingList li').first().waitFor();
        assert(await button.isVisible(), 'timer offline tersedia');
        await page.screenshot({ path: path.join(SHOTS, '06-offline-page.png'), fullPage: true });
        // Catat satu kontraksi lagi di halaman offline
        await button.click();
        await sleep(1200);
        await button.click();
        await page.locator('.swal2-popup:has-text("Tersimpan di perangkat")').waitFor({ timeout: 20000 });
        await swalClose();
        const queued = await page.evaluate(() => KKQueue.count(KK.state.userId));
        assert(queued === 2, `2 item di antrean IndexedDB (didapat ${queued})`);
    });

    await test('Kembali online: antrean tersinkron sekali, tanpa duplikasi', async () => {
        await context.setOffline(false);
        await page.evaluate(() => window.dispatchEvent(new Event('online')));
        await page.locator('.swal2-popup:has-text("Koneksi kembali")').waitFor({ timeout: 15000 });
        assert((await page.locator('.swal2-popup').innerText()).includes('2 catatan berhasil'), '2 catatan tersinkron');
        await page.click('.swal2-confirm');
        await page.waitForURL(BASE);
        const total = sql('SELECT COUNT(*) FROM contractions');
        assert(total === countBeforeOffline + 2, `jumlah data ${total}`);
        // Paksa sinkron ulang & kirim ulang uuid yang sama: tetap tidak ganda
        await page.evaluate(() => KK.syncQueue());
        assert(sql('SELECT COUNT(*) FROM contractions') === total, 'tidak ada duplikasi');
        assert(sql('SELECT COUNT(*) FROM (SELECT uuid FROM contractions GROUP BY uuid HAVING COUNT(*) > 1) d') === 0, 'uuid unik');
        assert(await page.locator('#pendingBadge.d-none').count() === 1, 'badge antrean hilang');
    });

    await test('Riwayat mobile: kartu responsif, detail, edit, filter', async () => {
        await page.goto(BASE + 'riwayat');
        await page.locator('[data-testid="history-table"]').waitFor();
        assert(!(await page.locator('.history-table thead').isVisible()), 'header tabel disembunyikan di HP');
        assert(await noHorizontalScroll(), 'tanpa scroll horizontal');
        await page.screenshot({ path: path.join(SHOTS, '07-history-mobile.png'), fullPage: true });
        await page.locator('[data-action="detail"]').first().click();
        await page.locator('#detailModal.show').waitFor();
        assert((await page.locator('#detailBody').innerText()).includes('Durasi'), 'detail tampil');
        await page.locator('#detailModal .btn-close').click();
        await page.locator('#detailModal').waitFor({ state: 'hidden' });
        await page.locator('[data-action="edit"]').first().click();
        await page.locator('#editModal.show').waitFor();
        await page.fill('#editNotes', 'Diedit lewat E2E');
        await page.click('#editForm button[type=submit]');
        await page.locator('.swal2-popup:has-text("Tersimpan")').waitFor();
        await page.waitForLoadState('load');
        await page.locator('text=Diedit lewat E2E').first().waitFor({ timeout: 8000 });
        // Filter tanggal tidak valid ditolak; tanggal hari ini berfungsi
        const today = await page.evaluate(() => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date()));
        await page.goto(BASE + 'riwayat?tanggal=' + today);
        assert((await page.locator('[data-testid="stat-total"]').innerText()) === String(sql('SELECT COUNT(*) FROM contractions')), 'filter hari ini');
        const res = await page.goto(BASE + 'riwayat?dari=2026-02-30');
        assert(res.status() === 422, 'tanggal tidak valid ditolak');
        await page.locator('[data-testid="filter-error"]').waitFor();
    });

    await test('Hapus kontraksi dengan konfirmasi SweetAlert2', async () => {
        await page.goto(BASE + 'riwayat');
        const before = sql('SELECT COUNT(*) FROM contractions');
        await page.locator('[data-action="delete"]').first().click();
        await page.locator('.swal2-popup:has-text("Hapus kontraksi?")').waitFor();
        await page.click('.swal2-confirm');
        await page.locator('.swal2-popup:has-text("Terhapus")').waitFor();
        await page.waitForLoadState('load');
        await sleep(1600);
        assert(sql('SELECT COUNT(*) FROM contractions') === before - 1, 'terhapus');
    });

    await test('Ekspor CSV mengunduh file', async () => {
        const [download] = await Promise.all([page.waitForEvent('download'), page.click('[data-testid="export-csv"]')]);
        assert(/^riwayat-kontraksi-.*\.csv$/.test(download.suggestedFilename()), 'nama file csv');
        const content = fs.readFileSync(await download.path(), 'utf8');
        assert(content.includes('Waktu Mulai') && content.split('\n').length >= 3, 'isi csv');
    });

    await test('Halaman grafik & profil tampil tanpa error', async () => {
        await page.goto(BASE + 'grafik');
        await page.locator('#durationChart').waitFor();
        assert(await noHorizontalScroll(), 'grafik responsif');
        await page.screenshot({ path: path.join(SHOTS, '08-analysis-mobile.png'), fullPage: true });
        await page.goto(BASE + 'profil');
        await page.fill('#nickname', 'Bunda E2E');
        await page.click('form[action$="profil"] button[type=submit]');
        await page.locator('[data-flash="success"]').waitFor();
        await page.goto(BASE + 'info');
        await page.screenshot({ path: path.join(SHOTS, '09-info-mobile.png'), fullPage: true });
    });

    await test('Tampilan desktop: sidebar tampil, navigasi bawah tersembunyi', async () => {
        const desk = await browser.newContext({ viewport: { width: 1366, height: 860 }, timezoneId: 'Asia/Jakarta', storageState: await context.storageState() });
        const p = await desk.newPage();
        await p.goto(BASE);
        assert(await p.locator('.app-sidebar').isVisible(), 'sidebar tampil');
        assert(!(await p.locator('.bottom-nav').isVisible()), 'navigasi bawah tersembunyi');
        await p.waitForTimeout(600);
        await p.screenshot({ path: path.join(SHOTS, '10-dashboard-desktop.png'), fullPage: true });
        await p.goto(BASE + 'riwayat');
        assert(await p.locator('.history-table thead').isVisible(), 'tabel penuh di desktop');
        await p.screenshot({ path: path.join(SHOTS, '11-history-desktop.png'), fullPage: true });
        await desk.close();
    });

    await test('Admin: tambah pengguna & reset password (mobile)', async () => {
        const adm = await browser.newContext({ ...devices['Pixel 7'], timezoneId: 'Asia/Jakarta' });
        const p = await adm.newPage();
        p.on('pageerror', (e) => consoleErrors.push('admin pageerror: ' + e.message));
        await p.goto(BASE + 'login');
        await p.fill('#email', 'admin@contoh.test');
        await p.fill('#password', 'rahasia123');
        await p.click('button[type=submit]');
        await p.waitForURL(BASE);
        await p.click('a[aria-label="Manajemen pengguna"]');
        await p.waitForURL(/\/admin$/);
        await p.locator('[data-testid="user-table"]').waitFor();
        await p.waitForFunction(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
        // Tambah pengguna
        await p.click('[data-testid="add-user"]');
        await p.locator('#createModal.show').waitFor();
        await p.fill('#newName', 'Pendamping Baru');
        await p.fill('#newEmail', 'baru@contoh.test');
        await p.click('#createModal button[type=submit]');
        await p.locator('[data-testid="temp-password"]').waitFor();
        const temp = (await p.locator('[data-testid="temp-password"]').innerText()).trim();
        assert(/^[A-Za-z2-9]{4}-[A-Za-z2-9]{4}-[A-Za-z2-9]{4}$/.test(temp), 'password sementara tampil: ' + temp);
        await p.screenshot({ path: path.join(SHOTS, '12-admin-temp-password.png') });
        await p.click('.swal2-confirm');
        // Reset password pengguna e2e
        await p.locator('tr[data-email="e2e@contoh.test"] [data-action="reset"]').click();
        await p.locator('.swal2-popup:has-text("Reset password?")').waitFor();
        await p.click('.swal2-confirm');
        await p.locator('.swal2-popup:has-text("Password direset")').waitFor();
        const temp2 = (await p.locator('[data-testid="temp-password"]').innerText()).trim();
        await p.click('.swal2-confirm');
        await p.screenshot({ path: path.join(SHOTS, '13-admin-users-mobile.png'), fullPage: true });
        // Sesi pengguna e2e di browser lain langsung berakhir
        await page.goto(BASE);
        assert(page.url().endsWith('/login'), 'sesi e2e berakhir setelah reset');
        // Login dengan password sementara -> wajib ganti password
        await page.fill('#email', 'e2e@contoh.test');
        await page.fill('#password', temp2);
        await page.click('button[type=submit]');
        await page.waitForURL(/\/ganti-password$/);
        await page.locator('[data-testid="must-change"]').waitFor();
        await page.fill('#current_password', temp2);
        await page.fill('#password', 'passwordbaru123');
        await page.fill('#password_confirmation', 'passwordbaru123');
        await page.click('form[action$="ganti-password"] button[type=submit]');
        await page.locator('[data-flash="success"]').waitFor();
        await page.goto(BASE);
        assert(await button.isVisible(), 'kembali bisa memakai timer');
        await adm.close();
    });

    await test('Tidak ada error JavaScript di konsol (di luar error jaringan saat offline)', async () => {
        const real = consoleErrors.filter((e) => !/ERR_INTERNET_DISCONNECTED|Failed to load resource|net::ERR|status of 4\d\d/.test(e));
        assert(real.length === 0, real.join(' | '));
    });

    await browser.close();
    server.kill();
    const total = passed + failures.length;
    console.log(`\n${failures.length ? '\x1b[31m' : '\x1b[32m'}${passed}/${total} lulus\x1b[0m`);
    process.exit(failures.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
