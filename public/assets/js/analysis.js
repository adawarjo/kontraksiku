/* Halaman Grafik & Analisis */
(function () {
    'use strict';
    var el = document.getElementById('analysisData');
    if (!el || !window.KKCharts) return;
    var data;
    try { data = JSON.parse(el.textContent); } catch (e) { return; }

    function emptyBox(canvas, text) {
        var box = canvas.parentElement;
        box.classList.remove('chart-box');
        box.innerHTML = '<div class="empty-state"><i class="bi bi-graph-up"></i><p class="mb-0">' + text + '</p></div>';
    }

    var dCanvas = document.getElementById('durationChart');
    var iCanvas = document.getElementById('intervalChart');
    if (!data.labels || data.labels.length === 0) {
        if (dCanvas) emptyBox(dCanvas, 'Belum ada kontraksi selesai di sesi ini.');
        if (iCanvas) emptyBox(iCanvas, 'Belum ada interval untuk ditampilkan.');
        return;
    }
    if (dCanvas) KKCharts.duration(dCanvas, data);
    var hasInterval = data.intervals.some(function (v) { return v !== null; });
    if (iCanvas) {
        if (hasInterval) KKCharts.interval(iCanvas, data);
        else emptyBox(iCanvas, 'Interval muncul mulai kontraksi kedua dalam sesi.');
    }
})();
