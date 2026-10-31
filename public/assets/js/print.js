/* Laporan cetak */
(function () {
    'use strict';
    var printBtn = document.querySelector('[data-print]');
    var closeBtn = document.querySelector('[data-close]');
    if (printBtn) printBtn.addEventListener('click', function () { window.print(); });
    if (closeBtn) closeBtn.addEventListener('click', function () {
        // Laporan biasanya dibuka di tab baru (tanpa riwayat) -> tutup tab; selain itu kembali
        if (window.opener || history.length <= 1) window.close(); else history.back();
    });

    // Grafik per sesi: gambar sekali dengan ukuran tetap lalu ganti menjadi <img>,
    // agar tidak terpotong/kosong saat browser mengubah ukuran halaman untuk dicetak.
    if (!window.KKCharts) return;
    document.querySelectorAll('[data-report-chart]').forEach(function (box) {
        var dataEl = box.querySelector('script[type="application/json"]');
        var canvas = box.querySelector('canvas');
        var data;
        try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }
        var chart = KKCharts.combined(canvas, data, { animation: false, responsive: false, devicePixelRatio: 2 });
        if (!chart) return;
        var img = new Image();
        img.className = 'report-chart-img';
        img.alt = canvas.getAttribute('aria-label') || '';
        img.src = chart.toBase64Image();
        chart.destroy();
        canvas.replaceWith(img);
    });
})();
