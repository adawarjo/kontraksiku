/* Halaman offline: status pengguna, sinkronisasi saat koneksi kembali, dan tombol muat ulang. */
(function () {
    'use strict';
    if (!window.KK) return;

    var noUser = document.getElementById('noUserNotice');
    var btn = document.getElementById('timerButton');
    if (!KK.state.userId) {
        if (noUser) noUser.classList.remove('d-none');
        if (btn) btn.disabled = true;
    }

    var reload = document.getElementById('reloadButton');
    if (reload) {
        reload.addEventListener('click', function () { window.location.href = KK.base; });
    }

    // Jumlah catatan di perangkat selama offline (untuk menghitung yang berhasil terkirim)
    var offlineCount = 0;
    document.addEventListener('kk:queue-changed', function (e) {
        if (!navigator.onLine) offlineCount = e.detail.count;
    });

    function countPending() {
        return KKQueue.all(KK.state.userId).then(function (items) {
            return items.filter(function (it) { return it.status !== 'error'; }).length;
        });
    }

    var handlingOnline = false;
    window.addEventListener('online', function () {
        // Event 'online' bisa terpicu lebih dari sekali; proses hanya satu kali
        if (handlingOnline) return;
        handlingOnline = true;
        var before = offlineCount;

        KK.refreshCsrf()
            .then(function () { return KK.syncQueue(); })
            .then(function () { return KK.syncQueue(); }) // pastikan putaran yang sudah berjalan selesai
            .then(countPending)
            .then(function (left) {
                var synced = Math.max(0, before - left);
                var text = left > 0
                    ? (synced ? synced + ' catatan tersimpan di server. ' : '') + left + ' catatan masih menunggu dan akan dicoba lagi.'
                    : (synced ? synced + ' catatan berhasil tersimpan di server.' : 'Anda dapat kembali ke aplikasi.');
                return Swal.fire({
                    icon: left > 0 ? 'info' : 'success', title: 'Koneksi kembali', text: text,
                    confirmButtonText: 'Buka aplikasi', allowOutsideClick: false
                });
            })
            .then(function (res) {
                handlingOnline = false;
                if (res.isConfirmed) window.location.href = KK.base;
            })
            .catch(function (err) {
                handlingOnline = false;
                console.warn('Sinkronisasi setelah online gagal', err && err.message);
                // Sesi login mungkin berakhir: data tetap di perangkat dan dikirim setelah login ulang
                window.location.href = KK.base;
            });
    });
})();
