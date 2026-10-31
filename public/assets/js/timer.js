/*
 * Timer kontraksi.
 *
 * Prinsip:
 *  - Waktu dihitung dari timestamp (Date.now() - started_at), bukan menambah angka per detik,
 *    sehingga tetap akurat walau tab berpindah, layar terkunci, atau halaman dimuat ulang.
 *  - Setiap aksi langsung ditulis ke antrean IndexedDB (tahan tutup browser/offline),
 *    lalu dikirim ke server. UUID dari perangkat membuat pengiriman ulang tidak menggandakan data.
 *  - Data hanya disebut "tersimpan di server" setelah server mengonfirmasi.
 */
(function () {
    'use strict';

    var $ = function (id) { return document.getElementById(id); };
    var btn = $('timerButton');
    if (!btn || !window.KK) return;

    var els = {
        display: $('timerDisplay'), sub: $('timerSub'), sync: $('timerSync'),
        cancel: $('cancelButton'), endSession: $('endSessionButton'), session: $('sessionStatus'),
        recent: $('recentList'), windowSummary: $('windowSummary'), statsScope: $('statsScope'), printSession: $('printSessionLink'),
        pendingSection: $('pendingSection'), pendingList: $('pendingList'),
        chart: $('dashboardChart'), chartBox: $('dashboardChartBox'), chartEmpty: $('dashboardChartEmpty')
    };

    var serverState = null;
    var active = null;        // { uuid, started_at, prev_start, synced }
    var tickHandle = null;
    var clickLock = false;    // pencegah klik ganda

    var initialEl = $('initialState');
    if (initialEl) {
        try { serverState = JSON.parse(initialEl.textContent); } catch (e) { serverState = null; }
    }

    function userId() { return KK.state.userId; }
    function activeKey() { return 'kk_active_' + userId(); }
    function lastStartKey() { return 'kk_last_start_' + userId(); }

    function loadLocalActive() {
        try { return JSON.parse(localStorage.getItem(activeKey()) || 'null'); } catch (e) { return null; }
    }
    function saveLocalActive() {
        try {
            if (active) localStorage.setItem(activeKey(), JSON.stringify(active));
            else localStorage.removeItem(activeKey());
        } catch (e) { /* abaikan */ }
    }
    function getLastStart() {
        var n = null;
        try { n = parseInt(localStorage.getItem(lastStartKey()) || '', 10); } catch (e) { /* abaikan */ }
        return isNaN(n) ? null : n;
    }
    function setLastStart(ms) { try { localStorage.setItem(lastStartKey(), String(ms)); } catch (e) { /* abaikan */ } }

    /* ---------------- Tampilan timer ---------------- */
    function tick() {
        if (!active) { els.display.textContent = '00:00'; return; }
        var sec = Math.max(0, Math.floor((Date.now() - active.started_at) / 1000));
        els.display.textContent = KK.fmtDuration(sec);
    }

    function renderTimer() {
        var running = !!active;
        btn.classList.toggle('is-running', running);
        btn.classList.toggle('is-idle', !running);
        btn.setAttribute('aria-pressed', running ? 'true' : 'false');
        btn.innerHTML = running
            ? '<i class="bi bi-stop-fill" aria-hidden="true"></i><span class="label">Akhiri Kontraksi</span>'
            : '<i class="bi bi-play-fill" aria-hidden="true"></i><span class="label">Mulai Kontraksi</span>';
        if (els.cancel) els.cancel.classList.toggle('d-none', !running);
        els.sub.textContent = running ? 'Mulai ' + KK.fmtTime(active.started_at) + ' WIB' : 'Siap mencatat';
        els.display.setAttribute('aria-label', running ? 'Kontraksi berlangsung' : 'Timer berhenti');

        if (tickHandle) { clearInterval(tickHandle); tickHandle = null; }
        tick();
        if (running) tickHandle = setInterval(tick, 250);
        renderSyncLabel();
    }

    function renderSyncLabel() {
        if (!els.sync) return;
        if (!active) { els.sync.innerHTML = ''; return; }
        els.sync.innerHTML = active.synced
            ? '<span class="badge text-bg-success-subtle text-success-emphasis"><i class="bi bi-cloud-check"></i> Waktu mulai tersimpan di server</span>'
            : '<span class="badge text-bg-warning"><i class="bi bi-hourglass"></i> Tersimpan di perangkat, menunggu server</span>';
    }

    /* ---------------- Tampilan statistik ---------------- */
    function setStat(key, value) {
        var el = document.querySelector('[data-stat="' + key + '"]');
        if (el) el.textContent = value;
    }

    function renderState(s) {
        if (!s) return;
        serverState = s;
        var sum = s.summary || {};
        setStat('count', sum.count || 0);
        setStat('last_duration', KK.fmtDuration(sum.last_duration));
        setStat('last_interval', KK.fmtDuration(sum.last_interval));
        setStat('avg_duration', KK.fmtDuration(sum.avg_duration));
        setStat('avg_interval', KK.fmtDuration(sum.avg_interval));
        setStat('w60_count', (s.window60 || {}).count || 0);

        var sessionActive = !!(s.session && s.session.is_active);
        if (els.statsScope) els.statsScope.textContent = sessionActive ? 'Sesi aktif' : 'Sesi terakhir';
        if (els.session) {
            els.session.innerHTML = sessionActive
                ? '<span class="dot dot-on"></span> Sesi aktif sejak ' + KK.escapeHtml(s.session.started_at)
                : '<span class="dot"></span> Belum ada sesi aktif. Tekan tombol untuk memulai.';
        }
        if (els.endSession) els.endSession.classList.toggle('d-none', !sessionActive);
        if (els.printSession) els.printSession.classList.toggle('d-none', !s.session);

        if (els.windowSummary && s.window60) {
            var w = s.window60;
            els.windowSummary.innerHTML = w.count === 0
                ? 'Belum ada kontraksi yang dicatat dalam ' + w.minutes + ' menit terakhir.'
                : w.count + ' kontraksi dalam ' + w.minutes + ' menit terakhir · rata-rata durasi <strong>' + KK.fmtDuration(w.avg_duration)
                  + '</strong> · rata-rata interval <strong>' + KK.fmtDuration(w.avg_interval) + '</strong>';
        }

        if (els.recent) {
            var items = s.recent || [];
            els.recent.innerHTML = items.length === 0
                ? '<li class="empty-state w-100 d-block"><i class="bi bi-inbox"></i>Belum ada catatan kontraksi.</li>'
                : items.map(function (c) {
                    return '<li><div><div class="fw-semibold">' + KK.escapeHtml(c.start_time) + ' <span class="meta">' + KK.escapeHtml(c.date) + '</span></div>'
                        + '<div class="meta">Interval ' + KK.escapeHtml(c.interval_label) + '</div></div>'
                        + (c.is_active
                            ? '<span class="badge text-bg-warning">Berlangsung</span>'
                            : '<span class="mono fw-semibold">' + KK.escapeHtml(c.duration_label) + '</span>')
                        + '</li>';
                }).join('');
        }

        if (els.chart && window.KKCharts) {
            var hasData = s.chart && s.chart.labels && s.chart.labels.length > 0;
            if (els.chartBox) els.chartBox.classList.toggle('d-none', !hasData);
            if (els.chartEmpty) els.chartEmpty.classList.toggle('d-none', hasData);
            if (hasData) KKCharts.combined(els.chart, s.chart);
        }

        // Perkiraan interval lokal memakai waktu mulai terakhir yang diketahui server
        if (sessionActive && s.recent && s.recent.length) {
            var latest = s.recent[0].started_at_ms;
            var known = getLastStart();
            if (!known || latest > known) setLastStart(latest);
        }
    }

    function renderPending() {
        if (!els.pendingList || !window.KKQueue) return;
        KKQueue.all(userId()).then(function (items) {
            els.pendingSection.classList.toggle('d-none', items.length === 0);
            els.pendingList.innerHTML = items.map(function (it) {
                var label = it.op === 'cancel' ? 'Pembatalan kontraksi' :
                    (it.ended_at ? 'Kontraksi ' + KK.fmtDuration((it.ended_at - it.started_at) / 1000) : 'Kontraksi sedang berlangsung');
                var status = it.status === 'error'
                    ? '<span class="badge text-bg-danger">Ditolak server</span> <span class="small text-danger">' + KK.escapeHtml(it.error) + '</span>'
                    : '<span class="badge text-bg-warning">Belum tersimpan di server</span>';
                return '<li class="list-group-item px-0 pending-item"><div><div class="fw-semibold">' + KK.fmtDate(it.started_at) + ' ' + KK.fmtTime(it.started_at)
                    + '</div><div class="small">' + label + '</div>' + status + '</div>'
                    + (it.status === 'error'
                        ? '<div class="d-flex gap-1"><button class="btn btn-sm btn-outline-secondary" data-retry="' + it.uuid + '">Coba lagi</button><button class="btn btn-sm btn-outline-danger" data-discard="' + it.uuid + '">Buang</button></div>'
                        : '')
                    + '</li>';
            }).join('');
        });
    }

    if (els.pendingList) {
        els.pendingList.addEventListener('click', function (e) {
            var retry = e.target.closest('[data-retry]');
            var discard = e.target.closest('[data-discard]');
            if (retry) {
                KKQueue.get(retry.dataset.retry).then(function (it) {
                    if (!it) return;
                    var fresh = Object.assign({}, it, { status: 'pending', error: null });
                    return KKQueue.put(fresh).then(flush);
                });
            }
            if (discard) {
                Swal.fire({
                    icon: 'warning', title: 'Buang catatan ini?', text: 'Catatan ini ditolak server dan akan dihapus dari perangkat.',
                    showCancelButton: true, confirmButtonText: 'Buang', cancelButtonText: 'Batal'
                }).then(function (r) {
                    if (!r.isConfirmed) return;
                    var uuid = discard.dataset.discard;
                    KKQueue.remove(uuid).then(function () {
                        if (active && active.uuid === uuid) { active = null; saveLocalActive(); renderTimer(); }
                        renderPending();
                    });
                });
            }
        });
    }

    /* ---------------- Sinkronisasi ---------------- */
    function flush() {
        return KK.syncQueue().then(function (result) {
            if (result.state) renderState(result.state);
            return reconcileAfterFlush().then(function () { return result; });
        });
    }

    /** Perbarui status "tersimpan" kontraksi aktif & tangani penolakan server. */
    function reconcileAfterFlush() {
        if (!window.KKQueue) return Promise.resolve();
        return KKQueue.all(userId()).then(function (items) {
            renderPending();
            if (!active) return;
            var item = items.find(function (it) { return it.uuid === active.uuid; });
            if (!item) {
                if (!active.synced) { active.synced = true; saveLocalActive(); renderSyncLabel(); }
                return;
            }
            if (item.status === 'error' && item.ended_at === null && item.op === 'sync') {
                // Mulai ditolak (mis. sudah ada kontraksi aktif dari perangkat lain)
                return KKQueue.remove(item.uuid).then(function () {
                    var serverActive = item.error_data && item.error_data.active;
                    active = serverActive ? { uuid: serverActive.uuid, started_at: serverActive.started_at_ms, prev_start: null, synced: true } : null;
                    saveLocalActive();
                    renderTimer();
                    renderPending();
                    KK.alertError(item.error + (serverActive ? ' Timer menampilkan kontraksi tersebut.' : ''), 'Tidak dapat memulai');
                });
            }
        });
    }

    function refreshState() {
        if (!navigator.onLine || document.visibilityState !== 'visible') return Promise.resolve();
        return KK.api('api/state').then(function (s) {
            renderState(s);
            return adoptServerActive(s);
        }).catch(function () { /* offline / sesi berakhir: biarkan data lokal */ });
    }

    /** Selaraskan kontraksi aktif lokal dengan server (refresh, tab lain, perangkat lain). */
    function adoptServerActive(s) {
        if (!window.KKQueue) return Promise.resolve();
        return KKQueue.all(userId()).then(function (items) {
            var queued = {};
            items.forEach(function (it) { queued[it.uuid] = it; });
            var srv = s.active;
            if (srv && !queued[srv.uuid]) {
                if (!active || active.uuid !== srv.uuid) {
                    if (active && !active.synced && queued[active.uuid]) return; // biarkan, akan ditangani saat sinkron
                    active = { uuid: srv.uuid, started_at: srv.started_at_ms, prev_start: null, synced: true };
                    saveLocalActive(); renderTimer();
                }
            } else if (!srv && active && active.synced && !queued[active.uuid] && !KK.state.inFlight[active.uuid]) {
                // Sudah diakhiri/dibatalkan dari tab atau perangkat lain
                active = null; saveLocalActive(); renderTimer();
            }
        });
    }

    /* ---------------- Aksi ---------------- */
    function lockClicks() {
        clickLock = true;
        btn.classList.add('is-busy');
        setTimeout(function () { clickLock = false; btn.classList.remove('is-busy'); }, 700);
    }

    function startContraction() {
        if (!userId()) {
            KK.alertError('Silakan login terlebih dahulu saat terhubung ke internet.', 'Belum login');
            return;
        }
        var now = Date.now();
        active = { uuid: KK.uuid(), started_at: now, prev_start: getLastStart(), synced: false };
        setLastStart(now);
        saveLocalActive();
        renderTimer();
        if (navigator.vibrate) navigator.vibrate(40);

        KKQueue.put({ uuid: active.uuid, user_id: userId(), op: 'sync', started_at: active.started_at, ended_at: null })
            .then(function () { renderPending(); return flush(); });
    }

    function endContraction() {
        var ended = active;
        var endedAt = Date.now();
        active = null;
        saveLocalActive();
        renderTimer();
        if (navigator.vibrate) navigator.vibrate([30, 40, 30]);

        var localDuration = Math.round((Math.floor(endedAt / 1000) - Math.floor(ended.started_at / 1000)));
        var localInterval = ended.prev_start && (ended.started_at - ended.prev_start) < 6 * 3600 * 1000
            ? Math.round((ended.started_at - ended.prev_start) / 1000) : null;

        KKQueue.put({ uuid: ended.uuid, user_id: userId(), op: 'sync', started_at: ended.started_at, ended_at: endedAt })
            .then(function () { renderPending(); return flush(); })
            .then(function () { return KKQueue.get(ended.uuid); })
            .then(function (item) {
                if (!item) {
                    // Server sudah mengonfirmasi penyimpanan
                    var saved = (serverState && serverState.recent || []).find(function (c) { return c.uuid === ended.uuid; });
                    showSummary(saved || null, ended, localDuration, localInterval, true);
                } else if (item.status === 'error') {
                    handleEndRejected(item, ended);
                } else {
                    showSummary(null, ended, localDuration, localInterval, false);
                }
            });
    }

    function handleEndRejected(item, ended) {
        // Kembalikan ke status berjalan agar pengguna dapat membatalkan/memperbaiki
        KKQueue.remove(item.uuid).then(function () {
            active = { uuid: ended.uuid, started_at: ended.started_at, prev_start: ended.prev_start, synced: true };
            saveLocalActive(); renderTimer(); renderPending();
            return refreshState();
        }).then(function () {
            KK.alertError(item.error + ' Gunakan tombol "Batalkan kontraksi ini" bila pencatatan keliru.', 'Gagal mengakhiri');
        });
    }

    function showSummary(saved, ended, localDuration, localInterval, confirmed) {
        var duration = saved ? saved.duration_seconds : localDuration;
        var interval = saved ? saved.interval_seconds : localInterval;
        var rows = '<div class="kk-summary">'
            + '<div><span>Mulai</span><strong>' + KK.fmtTime(ended.started_at) + '</strong></div>'
            + '<div><span>Selesai</span><strong>' + (saved ? KK.escapeHtml(saved.end_time) : KK.fmtTime(ended.started_at + localDuration * 1000)) + '</strong></div>'
            + '<div><span>Durasi</span><strong>' + KK.fmtDuration(duration) + '</strong></div>'
            + '<div><span>Interval' + (confirmed ? '' : ' (perkiraan)') + '</span><strong>' + (interval === null || interval === undefined ? 'Kontraksi pertama' : KK.fmtDuration(interval)) + '</strong></div>'
            + '</div>';

        if (confirmed) {
            Swal.fire({
                icon: 'success', title: 'Kontraksi tercatat',
                html: rows + '<p class="small text-success mt-3 mb-0"><i class="bi bi-cloud-check"></i> Tersimpan di server.</p>',
                input: 'textarea', inputPlaceholder: 'Tambah catatan (opsional), mis. nyeri di punggung',
                inputAttributes: { maxlength: 500, 'aria-label': 'Catatan kontraksi' },
                showCancelButton: true, confirmButtonText: 'Simpan catatan', cancelButtonText: 'Tutup',
                preConfirm: function (notes) {
                    notes = (notes || '').trim();
                    if (!notes || !saved) return true;
                    return KK.api('api/contractions/update', { method: 'POST', body: {
                        id: saved.id, start_date: saved.date_iso, start_time: saved.start_time,
                        end_date: saved.end_date_iso, end_time: saved.end_time, notes: notes
                    } }).then(function () { return 'saved'; }).catch(function (err) {
                        Swal.showValidationMessage(err.message);
                        return false;
                    });
                }
            }).then(function (r) {
                if (r.isConfirmed && r.value === 'saved') { KK.toast('success', 'Catatan tersimpan.'); refreshState(); }
            });
        } else {
            Swal.fire({
                icon: 'info', title: 'Tersimpan di perangkat',
                html: rows + '<p class="small text-warning-emphasis mt-3 mb-0"><i class="bi bi-cloud-slash"></i> <strong>Belum tersimpan di server.</strong> '
                    + 'Data akan dikirim otomatis ketika koneksi tersedia. Interval final dihitung server.</p>',
                confirmButtonText: 'Mengerti'
            });
        }
    }

    function cancelContraction() {
        if (!active) return;
        Swal.fire({
            icon: 'warning', title: 'Batalkan kontraksi ini?',
            text: 'Catatan kontraksi yang sedang berjalan akan dihapus.',
            showCancelButton: true, confirmButtonText: 'Ya, batalkan', cancelButtonText: 'Tidak', focusCancel: true
        }).then(function (r) {
            if (!r.isConfirmed || !active) return;
            var target = active;
            active = null;
            saveLocalActive();
            if (target.prev_start) setLastStart(target.prev_start);
            renderTimer();
            KKQueue.get(target.uuid).then(function (item) {
                var neverSent = !target.synced && item && !KK.state.inFlight[target.uuid];
                if (neverSent) {
                    // Belum pernah sampai ke server: cukup hapus dari perangkat
                    return KKQueue.remove(target.uuid).then(function () { KK.toast('success', 'Kontraksi dibatalkan.'); renderPending(); });
                }
                return KKQueue.put({ uuid: target.uuid, user_id: userId(), op: 'cancel', started_at: target.started_at, ended_at: null })
                    .then(flush)
                    .then(function () { return KKQueue.get(target.uuid); })
                    .then(function (left) {
                        if (!left) KK.toast('success', 'Kontraksi dibatalkan.');
                        else if (left.status === 'error') KK.alertError(left.error);
                        else KK.toast('info', 'Pembatalan akan dikirim saat online.');
                        renderPending();
                    });
            });
        });
    }

    function endSession() {
        if (active) { KK.alertError('Akhiri kontraksi yang sedang berlangsung terlebih dahulu.'); return; }
        Swal.fire({
            icon: 'question', title: 'Tutup sesi pencatatan?',
            text: 'Kontraksi berikutnya akan dicatat di sesi baru dan interval dimulai dari awal.',
            showCancelButton: true, confirmButtonText: 'Tutup sesi', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            els.endSession.disabled = true;
            KK.api('api/sessions/end', { method: 'POST', body: {} }).then(function (res) {
                renderState(res.state);
                try { localStorage.removeItem(lastStartKey()); } catch (e) { /* abaikan */ }
                KK.toast('success', res.message);
            }).catch(function (err) {
                KK.alertError(err.offline ? 'Tidak ada koneksi. Coba lagi saat online.' : err.message);
            }).finally(function () { els.endSession.disabled = false; });
        });
    }

    btn.addEventListener('click', function () {
        if (clickLock) return;
        lockClicks();
        if (active) endContraction(); else startContraction();
    });
    if (els.cancel) els.cancel.addEventListener('click', cancelContraction);
    if (els.endSession) els.endSession.addEventListener('click', endSession);

    // Tab kembali aktif: hitung ulang dari timestamp & selaraskan dengan server
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') { tick(); refreshState(); }
    });
    // Sinkron antar-tab
    window.addEventListener('storage', function (e) {
        if (e.key === activeKey()) { active = loadLocalActive(); renderTimer(); }
    });
    document.addEventListener('kk:synced', function (e) {
        if (e.detail && e.detail.state) renderState(e.detail.state);
        reconcileAfterFlush();
    });
    document.addEventListener('kk:queue-changed', renderPending);
    window.addEventListener('online', function () { refreshState(); });

    /* ---------------- Inisialisasi ---------------- */
    function init() {
        var local = loadLocalActive();
        if (serverState && serverState.active) {
            var srv = serverState.active;
            active = { uuid: srv.uuid, started_at: srv.started_at_ms, prev_start: local && local.uuid === srv.uuid ? local.prev_start : null, synced: true };
        } else {
            active = local;
        }
        renderTimer();
        if (serverState) renderState(serverState);

        var ready = window.KKQueue ? KKQueue.all(userId()) : Promise.resolve([]);
        ready.then(function (items) {
            var queued = items.find(function (it) { return active && it.uuid === active.uuid; });
            if (active && serverState && !serverState.active && active.synced && !queued) {
                // Server tidak lagi memiliki kontraksi ini (diakhiri/dibatalkan di tempat lain)
                active = null;
            }
            if (active && queued) active.synced = false;
            // Kontraksi aktif yang hilang dari localStorage tapi masih ada di antrean
            if (!active) {
                var openItem = items.find(function (it) { return it.op === 'sync' && it.ended_at === null && it.status !== 'error'; });
                if (openItem) active = { uuid: openItem.uuid, started_at: openItem.started_at, prev_start: null, synced: false };
            }
            saveLocalActive();
            renderTimer();
            renderPending();
            if (!serverState) refreshState();
            flush();
        });
        setInterval(refreshState, 30000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
