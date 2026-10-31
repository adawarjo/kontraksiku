# KontraksiKu

Aplikasi web (PWA) untuk membantu ibu hamil atau pendamping mencatat **waktu mulai, waktu selesai, durasi, dan interval kontraksi** menjelang persalinan.

Dibangun dengan PHP native (tanpa framework, tanpa Composer) dan MySQL/MariaDB, dengan antarmuka Bootstrap 5, Chart.js, dan SweetAlert2. Aplikasi dapat dipasang di HP sebagai PWA dan tetap bisa mencatat kontraksi saat offline; data otomatis disinkronkan ke server begitu koneksi kembali.

> **Penting:** KontraksiKu hanya alat bantu pencatatan, **bukan alat diagnosis medis**. Aplikasi tidak memprediksi waktu kelahiran dan tidak menentukan apakah persalinan sudah dimulai. Selalu ikuti arahan bidan/dokter.

---

## Daftar Isi
1. [Fitur](#fitur)
2. [Persyaratan](#persyaratan)
3. [Instalasi di Laragon (Windows)](#instalasi-di-laragon-windows)
4. [Instalasi di XAMPP (Windows)](#instalasi-di-xampp-windows)
5. [Instalasi di hosting PHP/MySQL](#instalasi-di-hosting-phpmysql)
6. [Menjalankan dengan server bawaan PHP](#menjalankan-dengan-server-bawaan-php)
7. [HTTPS & instalasi PWA](#https--instalasi-pwa)
8. [Cara kerja perhitungan](#cara-kerja-perhitungan)
9. [Mode offline & sinkronisasi](#mode-offline--sinkronisasi)
10. [Keamanan](#keamanan)
11. [Struktur folder](#struktur-folder)
12. [Pengujian](#pengujian)
13. [Troubleshooting](#troubleshooting)
14. [Keterbatasan](#keterbatasan)

---

## Fitur

| Area | Isi |
|---|---|
| **Dashboard** | Nama ibu, usia kehamilan (dari HPL), status sesi, jumlah kontraksi, durasi & interval terakhir, rata-rata, ringkasan 1 jam terakhir, grafik, riwayat terbaru, tombol timer besar |
| **Timer** | Mulai/Akhiri dengan satu tombol, berbasis timestamp (akurat walau pindah tab/refresh), anti klik ganda, batalkan dengan konfirmasi, ringkasan SweetAlert2 + tambah catatan |
| **Riwayat** | Tabel responsif (menjadi kartu di HP), filter rentang tanggal, cari tanggal, urutkan (waktu/durasi/interval), pagination, statistik rentang, detail/edit/hapus, ekspor CSV, cetak laporan |
| **Cetak laporan** | Siap cetak / simpan PDF (A4): ringkasan, lalu rincian **per sesi** (statistik, grafik durasi & interval, tabel kontraksi). Dari menu *Cetak Laporan* (semua data), tombol di *Riwayat* (sesuai filter), serta tombol *Cetak laporan sesi* di *Beranda* (sesi aktif/terakhir) dan *Grafik* (sesi terpilih) |
| **Profil** | Nama panggilan, HPL, tanggal mulai pencatatan, catatan, nama & kontak pendamping (semua opsional), hapus akun |
| **Grafik** | Durasi per kontraksi, interval antarkontraksi, perbandingan paruh awal vs akhir sesi, statistik 30 & 60 menit terakhir |
| **Info keselamatan** | Tanda bahaya, arahan menghubungi tenaga kesehatan |
| **Admin** | Manajemen pengguna (cari, tambah, ubah, nonaktifkan, hapus), reset password dengan password sementara, log audit — tanpa akses ke data kontraksi |
| **PWA** | Dapat dipasang, mode standalone, aset offline, halaman offline dengan timer, antrean IndexedDB |

Semua teks antarmuka berbahasa Indonesia; tanggal `DD-MM-YYYY`, waktu `HH:mm:ss`, zona waktu Asia/Jakarta (WIB).

---

## Persyaratan

- PHP **8.1+** dengan ekstensi `pdo_mysql`, `mbstring`, `json`
- MySQL **8.0+** atau MariaDB **10.4+** (XAMPP terbaru sudah memenuhi)
- Apache dengan `mod_rewrite` (bawaan XAMPP) — atau server bawaan PHP untuk pengembangan
- Browser modern (Chrome/Edge/Firefox/Safari terbaru)

Tidak perlu Composer atau Node.js untuk menjalankan aplikasi. Semua pustaka frontend (Bootstrap 5.3, Bootstrap Icons, SweetAlert2, Chart.js) sudah disertakan secara lokal di `public/assets/vendor/` sehingga tetap berfungsi offline.

---

## Instalasi di Laragon (Windows)

1. **Unduh kode** — buka *Terminal* dari Laragon (tombol **Terminal**), lalu:
   ```bat
   cd C:\laragon\www
   git clone https://github.com/adawarjo/kontraksiku.git
   cd kontraksiku
   ```
   Tanpa Git: unduh ZIP dari GitHub (**Code → Download ZIP**) dan ekstrak menjadi `C:\laragon\www\kontraksiku\`.
2. **Jalankan** Apache dan MySQL dengan tombol **Start All** di Laragon.
3. **Impor database** (di terminal yang sama):
   ```bat
   mysql -u root < database\schema.sql
   ```
   Atau buka **Database** di Laragon (HeidiSQL) → *File → Run SQL file* → pilih `database\schema.sql`.
4. **Konfigurasi** — buat `.env` dari contoh:
   ```bat
   copy .env.example .env
   ```
   Nilai bawaan (`DB_USERNAME=root`, `DB_PASSWORD=` kosong) sudah cocok dengan MySQL Laragon.
5. **Buat akun admin pertama:**
   ```bat
   php bin\admin.php create admin@contoh.com "Nama Admin"
   ```
   Password sementara ditampilkan sekali dan wajib diganti saat login pertama.
6. **Buka** `http://localhost/kontraksiku/` → login sebagai admin, atau klik **Daftar** untuk akun pengguna biasa.

---

## Instalasi di XAMPP (Windows)

1. **Salin proyek** ke `C:\xampp\htdocs\kontraksiku\` — atau `cd C:\xampp\htdocs` lalu `git clone https://github.com/adawarjo/kontraksiku.git`.
2. **Jalankan** Apache dan MySQL dari XAMPP Control Panel.
3. **Impor database**
   - Buka `http://localhost/phpmyadmin` → tab **Import** → pilih `database/schema.sql` → **Go**.
     File ini membuat database `kontraksiku` beserta semua tabel.
   - Atau lewat Command Prompt:
     ```bat
     cd C:\xampp\htdocs\kontraksiku
     C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
     ```
4. **Konfigurasi** — salin `.env.example` menjadi `.env`, lalu sesuaikan:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=kontraksiku
   DB_USERNAME=root
   DB_PASSWORD=
   APP_ALLOW_REGISTRATION=true
   ```
5. **Buat akun admin pertama** (Command Prompt):
   ```bat
   cd C:\xampp\htdocs\kontraksiku
   C:\xampp\php\php.exe bin\admin.php create admin@contoh.com "Nama Admin"
   ```
   Password sementara ditampilkan sekali dan wajib diganti saat login pertama.
6. **Buka** `http://localhost/kontraksiku/` (otomatis diteruskan ke folder `public/`) → login sebagai admin, atau klik **Daftar** untuk akun pengguna biasa.

> File `.htaccess` di root proyek meneruskan permintaan ke `public/` dan memblokir akses ke `app/`, `config/`, `database/`, `storage/`, `tests/`, dan `.env`. Pastikan `AllowOverride All` aktif (default XAMPP).

**Rekomendasi (lebih aman):** arahkan *DocumentRoot* atau *VirtualHost* langsung ke folder `public/`, misalnya di `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    ServerName kontraksiku.test
    DocumentRoot "C:/xampp/htdocs/kontraksiku/public"
    <Directory "C:/xampp/htdocs/kontraksiku/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
lalu tambahkan `127.0.0.1 kontraksiku.test` ke `C:\Windows\System32\drivers\etc\hosts`.

---

## Peran admin & manajemen pengguna

Ada dua peran: **Pengguna** (ibu/pendamping) dan **Admin**. Admin juga dapat memakai timer untuk dirinya sendiri.

**Yang dapat dilakukan admin** (menu *Manajemen Pengguna* di sidebar, atau ikon perisai di bilah atas HP):
- Mencari pengguna (nama/email) dan menyaring berdasarkan peran & status.
- **Menambah pengguna** — password diisi manual atau dibuat otomatis; pengguna wajib menggantinya saat login pertama. Cocok bila registrasi mandiri ditutup (`APP_ALLOW_REGISTRATION=false`).
- **Mengubah** nama, email, dan peran.
- **Reset password** — membuat password sementara (format `Abcd-Efgh-Jkmn`, ditampilkan **sekali** dengan tombol salin), memutus semua sesi login pengguna tersebut, membuka kunci login, dan memaksa pengguna membuat password baru.
- **Menonaktifkan/mengaktifkan** akun — akun nonaktif tidak bisa login dan sesinya langsung berakhir; data tetap tersimpan.
- **Menghapus** akun beserta seluruh datanya (permanen, dengan konfirmasi).
- Melihat **log audit** tindakan admin.

**Batasan demi privasi & keamanan:**
- Admin **tidak dapat melihat** catatan kontraksi maupun profil kehamilan pengguna lain.
- Admin tidak dapat menonaktifkan, menurunkan peran, mereset, atau menghapus akunnya sendiri dari halaman admin.
- Sistem selalu menyisakan **minimal satu admin aktif**.
- Semua aksi memakai POST + CSRF + konfirmasi SweetAlert2; password tidak pernah dicatat di log.

**Pengguna** dapat mengganti password sendiri di *Profil → Ganti password*; sesi di perangkat lain (termasuk perangkat yang dipercaya lewat *Ingat saya*) otomatis berakhir.

**Alat baris perintah** (`bin/admin.php`, hanya dari server — berguna bila admin lupa password):
```bash
php bin/admin.php create <email> "<Nama>"   # buat admin baru
php bin/admin.php promote <email>           # jadikan pengguna yang ada sebagai admin
php bin/admin.php demote <email>            # turunkan admin
php bin/admin.php reset-password <email>    # reset password siapa pun
php bin/admin.php list                      # daftar admin
```

### Pembaruan dari versi sebelumnya
Bila database dibuat sebelum fitur admin ada, jalankan migrasi **sekali**, lalu buat admin:
```bash
mysql -u root kontraksiku < database/migrations/001_admin_roles.sql
php bin/admin.php promote email-anda@contoh.com
```
(XAMPP: impor `database/migrations/001_admin_roles.sql` lewat phpMyAdmin.) Semua pengguna perlu login ulang satu kali setelah pembaruan.

Bila database dibuat sebelum fitur **Ingat saya** ada, jalankan juga migrasi 002 **sekali**:
```bash
mysql -u root kontraksiku < database/migrations/002_remember_tokens.sql
```

---

## Instalasi di hosting PHP/MySQL

1. Unggah seluruh folder proyek **di luar** `public_html`, lalu arahkan domain/subdomain ke folder `public/` (atau salin isi `public/` ke `public_html` dan ubah path `require` di `public/index.php` agar menunjuk ke folder `app/`).
2. Buat database di panel hosting, impor `database/schema.sql` (hapus/ubah baris `CREATE DATABASE`/`USE` bila nama database dari hosting berbeda).
3. Buat `.env` dari `.env.example`, isi kredensial database, set `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`.
4. Pastikan folder `storage/logs` dan `storage/sessions` dapat ditulis web server.
5. Aktifkan HTTPS (Let's Encrypt/AutoSSL dari hosting).

---

## Menjalankan dengan server bawaan PHP

Untuk pengembangan cepat (tanpa Apache):
```bash
cp .env.example .env            # lalu isi kredensial database
mysql -u root < database/schema.sql
php -S localhost:8000 -t public public/router.php
```
Buka `http://localhost:8000`. `localhost` dianggap *secure context*, sehingga service worker & PWA dapat diuji tanpa HTTPS.

---

## HTTPS & instalasi PWA

Service worker, mode offline, dan instalasi PWA **hanya berfungsi di HTTPS** atau di `http://localhost`. Alamat seperti `http://192.168.1.10/...` (mengakses laptop dari HP di jaringan yang sama) **tidak** memenuhi syarat browser.

Pilihan untuk uji di HP:
- **XAMPP + sertifikat lokal:** buat sertifikat dengan [mkcert](https://github.com/FiloSottile/mkcert), aktifkan `httpd-ssl.conf`, pasang root CA mkcert di HP.
- **Tunnel HTTPS** (mis. Cloudflare Tunnel / ngrok) ke `localhost`.
- **Hosting** dengan SSL.

**Memasang aplikasi:**
- **Android (Chrome):** buka situs → menu ⋮ → *Instal aplikasi* / *Tambahkan ke layar utama*. Tombol *Pasang aplikasi* juga muncul di halaman Profil bila browser mendukung.
- **iOS (Safari):** tombol Bagikan → *Tambahkan ke Layar Utama*.
- **Desktop (Chrome/Edge):** ikon instal di bilah alamat.

Setelah memperbarui file JS/CSS di server, **naikkan nilai `VERSION`** di `public/service-worker.js` agar cache lama dibersihkan.

---

## Cara kerja perhitungan

| Besaran | Rumus |
|---|---|
| Durasi | `waktu selesai − waktu mulai` |
| Interval | `waktu mulai kontraksi ini − waktu mulai kontraksi sebelumnya` (dalam sesi yang sama; **bukan** dari waktu selesai) |
| Kontraksi pertama dalam sesi | interval kosong |
| Rata-rata durasi | total durasi kontraksi selesai ÷ jumlah kontraksi selesai |
| Rata-rata interval | total interval valid ÷ jumlah interval valid |

- Interval **dihitung ulang otomatis** setelah tambah/edit/hapus, termasuk saat data offline tiba tidak berurutan.
- **Sesi** dimulai otomatis saat kontraksi pertama dicatat dan dapat ditutup dengan tombol *Tutup Sesi*; kontraksi berikutnya masuk sesi baru.
- Waktu dicatat dari **jam perangkat** (epoch ms), disimpan sebagai **UTC** di database, dan ditampilkan dalam **WIB**. Kontraksi yang melewati tengah malam tercatat pada tanggal mulainya.
- Validasi: waktu di masa depan (> 5 menit), selesai sebelum mulai, durasi > 3 jam, dan kontraksi yang bertumpuk ditolak.

---

## Mode offline & sinkronisasi

1. Setiap tekan tombol, data langsung ditulis ke **IndexedDB** dengan **UUID** unik dari perangkat, lalu dikirim ke server.
2. Bila koneksi terputus, data tetap di perangkat dengan label jelas **“Tersimpan di perangkat — belum tersimpan di server”** dan badge *N belum tersinkron*.
3. Data **hanya dihapus dari perangkat setelah server mengonfirmasi**. Ringkasan baru menyebut “Tersimpan di server” setelah respons server diterima.
4. Server bersifat **idempoten** berdasarkan UUID (`UNIQUE`), sehingga pengiriman ulang, klik ganda, atau sinkronisasi berulang tidak membuat data ganda.
5. Bila halaman dimuat saat offline, service worker menampilkan **halaman offline** yang tetap memiliki timer dan daftar antrean. Saat online kembali, antrean dikirim otomatis.
6. Data yang ditolak server (mis. bertabrakan) ditandai *Ditolak server* dengan pilihan **Coba lagi** atau **Buang**.

Service worker **tidak pernah meng-cache** halaman HTML (berisi data pribadi), halaman login, respons `/api/`, CSV, maupun laporan cetak — hanya aset statis.

---

## Keamanan

- Password: `password_hash()` / `password_verify()` (rehash otomatis).
- Session: cookie `HttpOnly`, `SameSite=Lax`, `Secure` di HTTPS, `use_strict_mode`, regenerasi ID saat login, timeout tidak aktif (default 12 jam agar tidak logout di tengah pencatatan).
- **Ingat saya** (opsional saat login): perangkat dipercaya selama 30 hari (`REMEMBER_LOGIN_DAYS`), tidak diperpanjang otomatis. Cookie `KKREMEMBER` (`HttpOnly`, `SameSite=Lax`, `Secure` di HTTPS) berisi `selector:validator`; database hanya menyimpan hash SHA-256 validator. Token dicabut saat logout dan otomatis tidak berlaku bila password diganti/direset atau akun dinonaktifkan.
- CSRF token untuk semua form dan API yang mengubah data (`_csrf` / header `X-CSRF-Token`); logout hanya via POST.
- PDO prepared statements, `ATTR_EMULATE_PREPARES=false`; kolom urut di-*whitelist*.
- Output di-escape (`htmlspecialchars`), catatan dibersihkan dari tag HTML, Content-Security-Policy ketat (tanpa inline script).
- Setiap query dibatasi `user_id`; pengguna lain mendapat 404 tanpa membocorkan keberadaan data.
- Transaksi + `SELECT … FOR UPDATE` pada baris pengguna + `UNIQUE` (`active_user_id`) mencegah dua kontraksi/sesi aktif bersamaan.
- Pembatasan login: 5 kali gagal per email (20 per IP) dalam 15 menit → terkunci sementara.
- Header `Cache-Control: no-store` pada semua respons dinamis; tidak ada data pribadi di URL.
- Error dicatat di `storage/logs/` (di luar folder public); pengguna hanya melihat pesan umum.
- Ekspor CSV terlindung dari *formula injection*.
- Hapus akun (dengan konfirmasi password) menghapus seluruh data secara *cascade*.

---

## Struktur folder

```
kontraksiku/
├── app/
│   ├── bootstrap.php          # autoloader, konfigurasi, helper
│   ├── core/                  # Router, Request, Response, Session, Csrf, Auth, Database, View, Env, Logger
│   ├── controllers/           # Auth, Dashboard, History, Profile, Page, Api
│   ├── models/                # User, PregnancyProfile, ContractionSession, Contraction, LoginAttempt, RememberToken
│   ├── services/              # ContractionService, StatsService, AuthService, RememberMeService, ReportService, TimeService, ContractionPresenter
│   ├── middleware/            # Auth, Guest, Csrf
│   └── views/                 # layouts, partials, halaman
├── bin/admin.php              # CLI: buat/promosikan admin, reset password
├── config/                    # app.php, database.php
├── database/schema.sql        # skema lengkap
├── database/migrations/       # pembaruan untuk instalasi lama
├── public/                    # SATU-SATUNYA folder yang diakses web
│   ├── index.php  router.php  .htaccess
│   ├── manifest.json  service-worker.js  offline.html
│   └── assets/ (css, js, icons, vendor)
├── routes/web.php
├── storage/ (logs, sessions)
├── tests/                     # uji PHP + E2E Playwright
├── .env.example  .env.testing.example  .gitignore  README.md
```

### Endpoint

| Metode | Path | Keterangan |
|---|---|---|
| GET/POST | `/login`, `/daftar` | Autentikasi |
| POST | `/logout` | Keluar |
| GET | `/`, `/riwayat`, `/grafik`, `/profil`, `/info` | Halaman |
| GET | `/ekspor-csv`, `/cetak-laporan` | Ekspor (mengikuti filter `dari`, `sampai`, `tanggal`) |
| GET | `/cetak-laporan?sesi=<id>` / `?sesi=terakhir` | Laporan cetak satu sesi (sesi milik pengguna lain → 404) |
| GET | `/api/state`, `/api/csrf` | Status dashboard, token |
| POST | `/api/contractions/sync` | Mulai/akhiri/sinkron (idempoten, body `{uuid, started_at, ended_at, notes}`) |
| POST | `/api/contractions/cancel` / `update` / `delete` | Batal, edit, hapus |
| POST | `/api/sessions/end` | Tutup sesi |
| GET/POST | `/ganti-password` | Ganti password sendiri |
| GET | `/admin` | Manajemen pengguna (admin) |
| POST | `/admin/pengguna/tambah` / `ubah` / `status` / `reset-password` / `hapus` | Aksi admin |

---

## Pengujian

Pengujian memakai database terpisah yang **dihapus & dibuat ulang** setiap run.

```bash
cp .env.testing.example .env.testing    # isi kredensial; nama DB wajib mengandung "test"

# 1) Uji layanan + integrasi HTTP (tanpa dependensi tambahan)
php tests/run.php            # semua
php tests/run.php service    # logika bisnis saja
php tests/run.php http       # alur HTTP nyata via server bawaan PHP

# 2) Uji browser end-to-end (opsional, butuh Node.js + Playwright)
cd tests/e2e && npm init -y && npm i playwright && npx playwright install chromium && cd ../..
node tests/e2e/e2e.js        # screenshot tersimpan di tests/e2e/screenshots/
```

> Uji HTTP menyalakan server bawaan PHP lewat shell Unix, jadi hanya berjalan di Linux/macOS/WSL. Di Windows (Laragon/XAMPP) jalankan `php tests/run.php service`.

Hasil terakhir: **74/74** uji PHP (PHP 8.3, MySQL 8.4) dan **19/19** uji E2E lulus (PHP 8.3, MariaDB 10.11, Chromium).

| # | Kebutuhan | Diuji di |
|---|---|---|
| 1 | Login & logout | HTTP, E2E |
| 2 | Timer mulai & berhenti | Service, HTTP, E2E |
| 3 | Durasi benar | Service, HTTP, E2E |
| 4 | Interval mulai-ke-mulai | Service, E2E |
| 5 | Kontraksi pertama tanpa interval | Service, E2E |
| 6 | Refresh tidak merusak timer | HTTP, E2E |
| 7 | Klik ganda tanpa duplikasi | Service, HTTP (5 permintaan paralel), E2E |
| 8 | Isolasi data antarpengguna | Service, HTTP |
| 9 | Filter riwayat | Service, HTTP, E2E |
| 10 | Ekspor CSV | HTTP, E2E |
| + | Cetak laporan per sesi (statistik & grafik per sesi, sesi pengguna lain ditolak), tombol di Beranda/Grafik/menu | Service, HTTP |
| 11 | Responsif layar kecil | E2E (Pixel 7 & 1366px, tanpa scroll horizontal) |
| 12 | PWA dapat diinstal | HTTP (manifest/ikon), E2E (service worker aktif, cache) |
| 13 | Data offline tidak diklaim tersimpan | E2E |
| 14 | Tanggal tidak valid ditolak | Service, HTTP, E2E |
| 15 | Sinkronisasi tanpa data ganda | Service, HTTP, E2E |
| + | Konflik timestamp, pergantian hari (tengah malam WIB), sesi belum selesai | Service |
| + | Ingat saya 30 hari: login pulih tanpa session, token dicabut saat logout, tidak berlaku setelah ganti password/nonaktif/kedaluwarsa | Service, HTTP |
| + | Admin: akses 403 untuk non-admin, tambah/ubah/nonaktif/hapus, reset password memutus sesi, wajib ganti password, minimal 1 admin, audit tanpa password | Service, HTTP, E2E |

---

## Troubleshooting

| Gejala | Solusi |
|---|---|
| Halaman 404 untuk `/riwayat` dll. | Aktifkan `mod_rewrite` dan `AllowOverride All`. Alternatif darurat: `index.php?route=/riwayat`. |
| “Terjadi kesalahan pada server” | Lihat `storage/logs/app-YYYY-MM-DD.log`. Biasanya kredensial `.env` salah atau database belum diimpor. |
| `SQLSTATE[HY000] [2002]` | MySQL belum berjalan, atau ganti `DB_HOST=localhost` ↔ `127.0.0.1`. |
| “Sesi formulir kedaluwarsa” (419) | Muat ulang halaman; terjadi bila halaman dibuka sangat lama atau cookie diblokir. |
| Tidak bisa login, pesan “Terlalu banyak percobaan” | Tunggu 15 menit, atau minta admin mereset password (sekaligus membuka kunci). |
| Lupa password | Minta admin mereset. Admin yang lupa: `php bin/admin.php reset-password <email>` di server. |
| Error “Unknown column 'role'” | Database versi lama: jalankan `database/migrations/001_admin_roles.sql`. |
| Tidak ada admin | `php bin/admin.php create <email> "<Nama>"` atau `promote <email>`. |
| Tombol instal / mode offline tidak muncul | Wajib HTTPS atau `localhost`. Cek DevTools → Application → Manifest/Service Workers. |
| Tampilan tidak berubah setelah update | Naikkan `VERSION` di `service-worker.js`, lalu muat ulang (atau *Clear site data*). |
| Badge “belum tersinkron” tidak hilang | Pastikan online & masih login; tekan **Sinkronkan**. Item “Ditolak server” perlu *Coba lagi* atau *Buang*. |
| CSV berantakan di Excel | File memakai pemisah `;` dan UTF-8 BOM (cocok untuk Excel berbahasa Indonesia). Di Excel versi lain gunakan Data → From Text/CSV dan pilih pemisah titik koma. |
| Jam kontraksi tidak sesuai | Waktu memakai jam perangkat; pastikan jam & zona waktu HP/PC benar. |
| Logout otomatis | Centang **Ingat saya** saat login, atau atur `SESSION_LIFETIME_MINUTES` di `.env`; folder `storage/sessions` harus dapat ditulis. |
| Error “Table … remember_tokens doesn't exist” | Database versi lama: jalankan `database/migrations/002_remember_tokens.sql`. |

---

## Keterbatasan

- Edit, hapus, tutup sesi, dan ekspor memerlukan koneksi; yang dapat dilakukan offline adalah mencatat (mulai/akhiri/batal) kontraksi.
- Interval pada ringkasan offline adalah **perkiraan**; nilai final dihitung server setelah sinkronisasi.
- Waktu bergantung pada jam perangkat; jam yang salah (>5 menit di depan server) akan ditolak.
- Tidak ada notifikasi push / pengingat terjadwal (sengaja, agar tidak memberi kesan diagnosis otomatis).
- Pembatasan login berbasis database sederhana; untuk produksi berskala besar pertimbangkan *rate limiting* di level web server.
- Reset password tidak dikirim lewat email; admin menyampaikan password sementara secara langsung (aplikasi tidak memerlukan server email).
- Admin tidak dapat melihat data kontraksi pengguna (disengaja); belum ada peran bidan/pendamping dengan akses lihat-saja.
- Satu akun dapat dipakai bersama ibu & pendamping di beberapa perangkat; kontraksi aktif disinkronkan antarperangkat saat online.
