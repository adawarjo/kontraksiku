/* Laporan cetak */
(function () {
    'use strict';
    var printBtn = document.querySelector('[data-print]');
    var closeBtn = document.querySelector('[data-close]');
    if (printBtn) printBtn.addEventListener('click', function () { window.print(); });
    if (closeBtn) closeBtn.addEventListener('click', function () {
        if (window.opener) window.close(); else history.back();
    });
})();
