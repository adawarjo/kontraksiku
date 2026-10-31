/*
 * Service worker KontraksiKu.
 *
 * Kebijakan cache:
 *  - Aset statis (CSS, JS, ikon, font, offline.html) -> disimpan saat instalasi (cache-first).
 *  - Halaman HTML (berisi data pribadi), halaman login, dan semua /api/ -> SELALU dari jaringan,
 *    TIDAK PERNAH disimpan di cache. Bila jaringan gagal saat navigasi, tampilkan offline.html
 *    yang menyediakan timer offline dengan antrean IndexedDB.
 *  - Ekspor CSV dan laporan cetak tidak di-cache.
 */
'use strict';

var VERSION = 'kontraksiku-v1.2.0';
var STATIC_CACHE = VERSION + '-static';

var PRECACHE = [
    'offline.html',
    'manifest.json',
    'assets/css/app.css',
    'assets/css/print.css',
    'assets/js/app.js',
    'assets/js/queue.js',
    'assets/js/timer.js',
    'assets/js/charts.js',
    'assets/js/offline.js',
    'assets/js/pwa-register.js',
    'assets/vendor/bootstrap/bootstrap.min.css',
    'assets/vendor/bootstrap/bootstrap.bundle.min.js',
    'assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
    'assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
    'assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff',
    'assets/vendor/sweetalert2/sweetalert2.min.css',
    'assets/vendor/sweetalert2/sweetalert2.min.js',
    'assets/vendor/chartjs/chart.umd.min.js',
    'assets/icons/icon-192.png',
    'assets/icons/icon-512.png'
];

// Path relatif terhadap lokasi service worker (mendukung instalasi di subfolder XAMPP)
var SCOPE = new URL('./', self.location).pathname;

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(STATIC_CACHE).then(function (cache) {
            return cache.addAll(PRECACHE.map(function (p) { return new Request(p, { cache: 'reload' }); }));
        }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) {
                return k.indexOf('kontraksiku-') === 0 && k !== STATIC_CACHE;
            }).map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

function isStaticAsset(url) {
    var path = url.pathname.slice(SCOPE.length);
    return path.indexOf('assets/') === 0 || path === 'manifest.json' || path === 'offline.html';
}

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') return; // POST (API, login, form) langsung ke jaringan
    var url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // API & data pribadi: jaringan saja, tanpa cache
    if (url.pathname.indexOf(SCOPE + 'api/') === 0) return;

    // Navigasi halaman: jaringan saja; bila gagal tampilkan halaman offline
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(function () {
                return caches.match('offline.html').then(function (res) {
                    return res || new Response('Anda sedang offline.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
                });
            })
        );
        return;
    }

    // Aset statis: URL berversi (?v=) dicocokkan persis -> cache-first.
    // Bila belum ada di cache: ambil dari jaringan lalu simpan; bila offline pakai versi mana pun yang tersimpan.
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.open(STATIC_CACHE).then(function (cache) {
                return cache.match(req).then(function (cached) {
                    if (cached) return cached;
                    return fetch(req).then(function (res) {
                        if (res && res.ok && res.type === 'basic') cache.put(req, res.clone());
                        return res;
                    }).catch(function () {
                        return cache.match(req, { ignoreSearch: true }).then(function (any) {
                            return any || Response.error();
                        });
                    });
                });
            })
        );
    }
});

self.addEventListener('message', function (event) {
    if (event.data === 'skipWaiting') self.skipWaiting();
});
