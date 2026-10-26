/* Halaman login/daftar: daftarkan service worker & cegah kirim ganda. */
(function () {
    'use strict';
    var src = (document.currentScript && document.currentScript.src) || '';
    var base = src.replace(/assets\/js\/pwa-register\.js.*$/, '') || '/';

    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(base + 'service-worker.js', { scope: base }).catch(function () { /* abaikan */ });
        });
    }

    document.querySelectorAll('form[data-once]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.submitted === '1') { e.preventDefault(); return; }
            form.dataset.submitted = '1';
            form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
        });
    });
})();
