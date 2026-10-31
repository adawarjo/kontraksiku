/* KKCharts - grafik Chart.js untuk durasi & interval (nilai dalam detik, label mm:dd). */
(function (global) {
    'use strict';

    var ROSE = '#c2577a', ROSE_FILL = 'rgba(194, 87, 122, .55)';
    var SAGE = '#2f7d6d', SAGE_FILL = 'rgba(47, 125, 109, .15)';
    var instances = {};

    // Halaman laporan cetak tidak memuat app.js (KK), jadi sediakan format mm:dd sendiri
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fmt(v) {
        if (global.KK) return KK.fmtDuration(v);
        v = Math.max(0, Math.round(v));
        var h = Math.floor(v / 3600), m = Math.floor((v % 3600) / 60), s = v % 60;
        return h > 0 ? h + ':' + pad(m) + ':' + pad(s) : pad(m) + ':' + pad(s);
    }

    function baseOptions(extra) {
        return Object.assign({
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 250 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { labels: { boxWidth: 12, usePointStyle: true } },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ctx.dataset.label + ': ' + (ctx.parsed.y === null ? '-' : fmt(ctx.parsed.y));
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }, grid: { display: false } }
            }
        }, extra || {});
    }

    function timeAxis(title, position, grid) {
        return {
            position: position || 'left', beginAtZero: true,
            title: { display: true, text: title },
            grid: { display: grid !== false, color: 'rgba(0,0,0,.05)' },
            ticks: { precision: 0, callback: function (v) { return fmt(v); } }
        };
    }

    function draw(canvas, config) {
        if (!canvas || !global.Chart) return null;
        var key = canvas.id;
        if (instances[key]) {
            instances[key].data = config.data;
            instances[key].update();
            return instances[key];
        }
        instances[key] = new Chart(canvas, config);
        return instances[key];
    }

    global.KKCharts = {
        /** Grafik gabungan dashboard & laporan cetak: batang durasi + garis interval. */
        combined: function (canvas, data, extra) {
            var opts = baseOptions(extra);
            opts.scales.y = timeAxis('Durasi', 'left');
            opts.scales.y1 = timeAxis('Interval', 'right', false);
            return draw(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        { type: 'bar', label: 'Durasi', data: data.durations, backgroundColor: ROSE_FILL, borderColor: ROSE, borderWidth: 1, borderRadius: 6, yAxisID: 'y' },
                        { type: 'line', label: 'Interval', data: data.intervals, borderColor: SAGE, backgroundColor: SAGE, pointRadius: 3, tension: .25, spanGaps: true, yAxisID: 'y1' }
                    ]
                },
                options: opts
            });
        },
        duration: function (canvas, data) {
            var opts = baseOptions();
            opts.scales.y = timeAxis('Durasi (mm:dd)');
            return draw(canvas, {
                type: 'bar',
                data: { labels: data.labels, datasets: [{ label: 'Durasi', data: data.durations, backgroundColor: ROSE_FILL, borderColor: ROSE, borderWidth: 1, borderRadius: 6 }] },
                options: opts
            });
        },
        interval: function (canvas, data) {
            var opts = baseOptions();
            opts.scales.y = timeAxis('Interval (mm:dd)');
            return draw(canvas, {
                type: 'line',
                data: { labels: data.labels, datasets: [{ label: 'Interval', data: data.intervals, borderColor: SAGE, backgroundColor: SAGE_FILL, fill: true, pointRadius: 3, tension: .25, spanGaps: true }] },
                options: opts
            });
        }
    };
})(window);
