/*
 * KK - utilitas bersama: API, format waktu, status koneksi, sinkronisasi antrean,
 * pendaftaran service worker, dan perilaku formulir.
 */
(function (global) {
    'use strict';

    var TZ = 'Asia/Jakarta';

    // Base aplikasi diturunkan dari lokasi file ini (…/assets/js/app.js) agar juga
    // berfungsi di halaman offline statis.
    var scriptSrc = (document.currentScript && document.currentScript.src) || '';
    var BASE = scriptSrc ? scriptSrc.replace(/assets\/js\/app\.js.*$/, '') : (document.querySelector('meta[name="app-base"]') || {}).content || '/';
    if (BASE.slice(-1) !== '/') BASE += '/';

    function meta(name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : null;
    }

    var state = {
        csrf: meta('csrf-token'),
        userId: meta('app-user') || null,
        syncing: false
    };

    // Simpan ID pengguna terakhir agar halaman offline tahu milik siapa antrean.
    try {
        if (state.userId) localStorage.setItem('kk_user', state.userId);
        else state.userId = localStorage.getItem('kk_user');
    } catch (e) { /* localStorage tidak tersedia */ }

    /* ---------------- Format ---------------- */
    function pad(n) { return String(n).padStart(2, '0'); }

    function fmtDuration(sec) {
        if (sec === null || sec === undefined || isNaN(sec)) return '-';
        sec = Math.max(0, Math.round(sec));
        var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
        return h > 0 ? h + ':' + pad(m) + ':' + pad(s) : pad(m) + ':' + pad(s);
    }

    function parts(ms) {
        var f = new Intl.DateTimeFormat('en-GB', {
            timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
        });
        var o = {};
        f.formatToParts(new Date(ms)).forEach(function (p) { o[p.type] = p.value; });
        if (o.hour === '24') o.hour = '00';
        return o;
    }
    function fmtDate(ms) { var p = parts(ms); return p.day + '-' + p.month + '-' + p.year; }
    function fmtTime(ms) { var p = parts(ms); return p.hour + ':' + p.minute + ':' + p.second; }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function uuid() {
        if (global.crypto && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
        var b = new Uint8Array(16);
        crypto.getRandomValues(b);
        b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
        var h = Array.prototype.map.call(b, function (x) { return x.toString(16).padStart(2, '0'); }).join('');
        return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
    }

    /* ---------------- API ---------------- */
    function ApiError(message, status, data) {
        this.message = message; this.status = status; this.data = data || {};
        this.offline = status === 0;
    }
    ApiError.prototype = Object.create(Error.prototype);

    function refreshCsrf() {
        return fetch(BASE + 'api/csrf', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (!r.ok) throw new ApiError(r.status === 401 ? 'Sesi login berakhir.' : 'Gagal mengambil token.', r.status);
                return r.json();
            })
            .then(function (d) {
                state.csrf = d.token;
                if (d.user_id) { state.userId = String(d.user_id); try { localStorage.setItem('kk_user', state.userId); } catch (e) {} }
                return d.token;
            });
    }

    function api(path, options, retried) {
        options = options || {};
        var method = options.method || 'GET';
        var headers = { 'Accept': 'application/json' };
        if (method !== 'GET') {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = state.csrf || '';
        }
        var controller = 'AbortController' in global ? new AbortController() : null;
        var timer = controller ? setTimeout(function () { controller.abort(); }, options.timeout || 15000) : null;

        var prep = (method !== 'GET' && !state.csrf) ? refreshCsrf().catch(function () { return null; }) : Promise.resolve();
        return prep.then(function () {
            headers['X-CSRF-Token'] = state.csrf || '';
            return fetch(BASE + path, {
                method: method,
                headers: headers,
                credentials: 'same-origin',
                cache: 'no-store',
                body: options.body ? JSON.stringify(options.body) : undefined,
                signal: controller ? controller.signal : undefined
            });
        }).then(function (res) {
            if (timer) clearTimeout(timer);
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (res.ok) return data;
                if (res.status === 419 && !retried) {
                    return refreshCsrf().then(function () { return api(path, options, true); });
                }
                throw new ApiError(data.message || ('Terjadi kesalahan (' + res.status + ').'), res.status, data);
            });
        }, function (err) {
            if (timer) clearTimeout(timer);
            if (err instanceof ApiError) throw err;
            throw new ApiError('Tidak dapat terhubung ke server.', 0);
        });
    }

    /* ---------------- Notifikasi ---------------- */
    var Toast = global.Swal ? Swal.mixin({ toast: true, position: 'top', showConfirmButton: false, timer: 3000, timerProgressBar: true }) : null;
    function toast(icon, title) {
        if (Toast) Toast.fire({ icon: icon, title: title });
    }
    function alertError(message, title) {
        if (global.Swal) return Swal.fire({ icon: 'error', title: title || 'Gagal', text: message, confirmButtonText: 'Tutup' });
        alert(message);
        return Promise.resolve();
    }

    /* ---------------- Status koneksi & antrean ---------------- */
    function updateConnectionBadge() {
        var badge = document.getElementById('connectionBadge');
        if (!badge) return;
        var online = navigator.onLine;
        badge.className = 'badge rounded-pill ' + (online ? 'text-bg-success-subtle text-success-emphasis' : 'text-bg-secondary');
        badge.innerHTML = online ? '<i class="bi bi-wifi"></i> <span class="label">Online</span>' : '<i class="bi bi-wifi-off"></i> <span class="label">Offline</span>';
    }

    function updatePendingBadge() {
        if (!global.KKQueue) return Promise.resolve(0);
        return KKQueue.count(state.userId).then(function (n) {
            var badge = document.getElementById('pendingBadge');
            if (badge) {
                badge.classList.toggle('d-none', n === 0);
                var c = badge.querySelector('.count');
                if (c) c.textContent = n;
            }
            document.dispatchEvent(new CustomEvent('kk:queue-changed', { detail: { count: n } }));
            return n;
        });
    }

    /**
     * Kirim antrean ke server secara berurutan. Item hanya dihapus dari perangkat
     * setelah server mengonfirmasi penyimpanan. UUID membuat pengiriman ulang aman (idempoten).
     */
    var currentSync = null;   // promise sinkronisasi yang sedang berjalan
    var rerunSync = false;    // ada perubahan baru selama sinkronisasi berjalan
    state.inFlight = {};      // uuid yang sedang dikirim ke server

    function syncQueue() {
        if (currentSync) {
            rerunSync = true;
            return currentSync;
        }
        if (!navigator.onLine || !global.KKQueue || !state.userId) {
            return Promise.resolve({ synced: 0, offline: !navigator.onLine });
        }
        var total = { synced: 0, state: null, lastError: null };
        currentSync = runSyncPass(total).then(function loop(stopped) {
            if (rerunSync && !stopped) {
                rerunSync = false;
                return runSyncPass(total).then(loop);
            }
            return stopped;
        }).then(function (stopped) {
            currentSync = null;
            rerunSync = false;
            updatePendingBadge();
            if (total.synced > 0) {
                document.dispatchEvent(new CustomEvent('kk:synced', { detail: { count: total.synced, state: total.state } }));
            }
            return { synced: total.synced, state: total.state, stopped: stopped, lastError: total.lastError };
        }, function (e) {
            currentSync = null;
            updatePendingBadge();
            return { synced: total.synced, error: e };
        });
        return currentSync;
    }

    /** Satu putaran pengiriman. Resolve true bila berhenti karena offline/kesalahan server. */
    function runSyncPass(total) {
        return KKQueue.all(state.userId).then(function (items) {
            items = items.filter(function (it) { return it.status !== 'error'; });
            return items.reduce(function (p, item) {
                return p.then(function (stop) {
                    if (stop) return true;
                    state.inFlight[item.uuid] = true;
                    var req = item.op === 'cancel'
                        ? api('api/contractions/cancel', { method: 'POST', body: { uuid: item.uuid } })
                        : api('api/contractions/sync', { method: 'POST', body: { uuid: item.uuid, started_at: item.started_at, ended_at: item.ended_at, notes: item.notes } });
                    return req.then(function (res) {
                        delete state.inFlight[item.uuid];
                        total.state = res.state || total.state;
                        total.synced++;
                        return KKQueue.get(item.uuid).then(function (current) {
                            // Item berubah selama pengiriman (mis. kontraksi diakhiri) -> versi terbaru dikirim di putaran berikutnya
                            if (current && current.updated_at !== item.updated_at) { rerunSync = true; return false; }
                            return KKQueue.remove(item.uuid).then(function () { return false; });
                        });
                    }, function (err) {
                        delete state.inFlight[item.uuid];
                        if (err.offline || err.status >= 500 || err.status === 401 || err.status === 403 || err.status === 419) {
                            total.lastError = err;
                            return true; // berhenti, coba lagi nanti; data tetap di perangkat
                        }
                        // 409/422: data ditolak server -> tandai agar pengguna dapat memutuskan
                        return KKQueue.put(Object.assign({}, item, {
                            status: 'error', error: err.message, error_status: err.status, error_data: err.data || null
                        })).then(function () { return false; });
                    });
                });
            }, Promise.resolve(false));
        });
    }

    /* ---------------- Service worker & instalasi PWA ---------------- */
    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return;
        if (!global.isSecureContext) {
            console.info('Service worker membutuhkan HTTPS atau localhost.');
            return;
        }
        navigator.serviceWorker.register(BASE + 'service-worker.js', { scope: BASE }).catch(function (e) {
            console.warn('Gagal mendaftarkan service worker', e);
        });
    }

    var deferredPrompt = null;
    global.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        var btn = document.getElementById('installButton');
        if (btn) btn.classList.remove('d-none');
    });

    /* ---------------- Perilaku formulir ---------------- */
    function bindForms() {
        // Cegah kirim ganda
        document.querySelectorAll('form[data-once]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.submitted === '1') { e.preventDefault(); return; }
                form.dataset.submitted = '1';
                form.querySelectorAll('button[type="submit"]').forEach(function (b) {
                    b.disabled = true;
                    b.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>');
                });
            });
        });

        // Konfirmasi SweetAlert untuk aksi berbahaya
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === '1') return;
                e.preventDefault();
                Swal.fire({
                    icon: 'warning', title: form.dataset.confirmTitle || 'Yakin?', text: form.dataset.confirm,
                    showCancelButton: true, confirmButtonText: 'Ya, lanjutkan', cancelButtonText: 'Batal', focusCancel: true
                }).then(function (r) {
                    if (r.isConfirmed) { form.dataset.confirmed = '1'; form.requestSubmit ? form.requestSubmit() : form.submit(); }
                });
            });
        });

        // Peringatan bila keluar dengan data yang belum tersinkron
        document.querySelectorAll('form[data-logout-form]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === '1' || !global.KKQueue) return;
                e.preventDefault();
                KKQueue.count(state.userId).then(function (n) {
                    if (n === 0) { form.dataset.confirmed = '1'; form.submit(); return; }
                    Swal.fire({
                        icon: 'warning', title: 'Ada data belum tersinkron',
                        html: n + ' catatan kontraksi masih tersimpan di perangkat dan <strong>belum tersimpan di server</strong>. Data akan tetap di perangkat dan dikirim saat Anda login kembali.',
                        showCancelButton: true, confirmButtonText: 'Tetap keluar', cancelButtonText: 'Batal'
                    }).then(function (r) { if (r.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); } });
                });
            });
        });

        document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
            sel.addEventListener('change', function () { sel.form.submit(); });
        });

        var installBtn = document.getElementById('installButton');
        if (installBtn) {
            if (deferredPrompt) installBtn.classList.remove('d-none');
            installBtn.addEventListener('click', function () {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                deferredPrompt.userChoice.finally(function () { deferredPrompt = null; installBtn.classList.add('d-none'); });
            });
        }

        document.querySelectorAll('[data-sync-now]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!navigator.onLine) { toast('info', 'Perangkat sedang offline.'); return; }
                btn.disabled = true;
                syncQueue().then(function (r) {
                    btn.disabled = false;
                    if (r.synced > 0) toast('success', r.synced + ' catatan berhasil tersimpan di server.');
                    else toast('info', 'Belum ada data yang dapat dikirim.');
                });
            });
        });
    }

    global.KK = {
        base: BASE,
        tz: TZ,
        state: state,
        api: api,
        refreshCsrf: refreshCsrf,
        uuid: uuid,
        fmtDuration: fmtDuration,
        fmtDate: fmtDate,
        fmtTime: fmtTime,
        escapeHtml: escapeHtml,
        toast: toast,
        alertError: alertError,
        syncQueue: syncQueue,
        updatePendingBadge: updatePendingBadge
    };

    global.addEventListener('online', function () { updateConnectionBadge(); syncQueue(); });
    global.addEventListener('offline', updateConnectionBadge);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') syncQueue();
    });
    if (global.KKQueue) KKQueue.onChange(updatePendingBadge);

    document.addEventListener('DOMContentLoaded', function () {
        updateConnectionBadge();
        updatePendingBadge();
        bindForms();
        registerServiceWorker();
        syncQueue();
        setInterval(syncQueue, 30000);
    });
})(window);
