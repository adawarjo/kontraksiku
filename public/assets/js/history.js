/* Halaman Riwayat: detail, edit, dan hapus kontraksi. */
(function () {
    'use strict';
    var table = document.querySelector('[data-testid="history-table"]');
    if (!table || !window.KK) return;

    var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    var editModalEl = document.getElementById('editModal');
    var editModal = new bootstrap.Modal(editModalEl);
    var form = document.getElementById('editForm');
    var saving = false;

    function rowData(btn) {
        var tr = btn.closest('tr[data-row]');
        try { return JSON.parse(tr.dataset.contraction); } catch (e) { return null; }
    }

    function showDetail(c) {
        var items = [
            ['Tanggal', c.date],
            ['Waktu mulai', c.start_time + ' WIB'],
            ['Waktu selesai', c.is_active ? 'Masih berlangsung' : c.end_time + ' WIB'],
            ['Durasi', c.duration_label + (c.duration_seconds !== null ? ' (' + c.duration_seconds + ' detik)' : '')],
            ['Interval', c.interval_seconds === null ? 'Kontraksi pertama dalam sesi' : c.interval_label + ' (' + c.interval_seconds + ' detik)'],
            ['Catatan', c.notes || '-']
        ];
        document.getElementById('detailBody').innerHTML = items.map(function (it) {
            return '<dt class="col-5 fw-normal text-muted">' + KK.escapeHtml(it[0]) + '</dt><dd class="col-7">' + KK.escapeHtml(it[1]) + '</dd>';
        }).join('');
        detailModal.show();
    }

    function showEdit(c) {
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.elements.id.value = c.id;
        form.elements.start_date.value = c.date_iso;
        form.elements.start_time.value = c.start_time;
        form.elements.end_date.value = c.end_date_iso || c.date_iso;
        form.elements.end_time.value = c.end_time || '';
        form.elements.notes.value = c.notes || '';
        editModal.show();
    }

    function confirmDelete(c) {
        Swal.fire({
            icon: 'warning', title: 'Hapus kontraksi?',
            html: 'Data tanggal <strong>' + KK.escapeHtml(c.date) + '</strong> pukul <strong>' + KK.escapeHtml(c.start_time) + '</strong> akan dihapus. Interval kontraksi berikutnya dihitung ulang.',
            showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal', focusCancel: true,
            showLoaderOnConfirm: true,
            preConfirm: function () {
                return KK.api('api/contractions/delete', { method: 'POST', body: { id: c.id } }).catch(function (err) {
                    Swal.showValidationMessage(err.offline ? 'Tidak ada koneksi. Penghapusan memerlukan koneksi internet.' : err.message);
                    return false;
                });
            },
            allowOutsideClick: function () { return !Swal.isLoading(); }
        }).then(function (r) {
            if (r.isConfirmed && r.value) {
                Swal.fire({ icon: 'success', title: 'Terhapus', text: r.value.message, timer: 1400, showConfirmButton: false })
                    .then(function () { location.reload(); });
            }
        });
    }

    table.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action]');
        if (!btn || btn.disabled) return;
        var c = rowData(btn);
        if (!c) return;
        if (btn.dataset.action === 'detail') showDetail(c);
        if (btn.dataset.action === 'edit') showEdit(c);
        if (btn.dataset.action === 'delete') confirmDelete(c);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (saving) return;
        var f = form.elements;
        var missing = ['start_date', 'start_time', 'end_date', 'end_time'].filter(function (n) {
            var empty = !f[n].value;
            f[n].classList.toggle('is-invalid', empty);
            return empty;
        });
        if (missing.length) { KK.alertError('Lengkapi tanggal dan jam mulai/selesai.', 'Data belum lengkap'); return; }
        var start = new Date(f.start_date.value + 'T' + f.start_time.value);
        var end = new Date(f.end_date.value + 'T' + f.end_time.value);
        if (!(end > start)) {
            f.end_time.classList.add('is-invalid');
            KK.alertError('Waktu selesai harus setelah waktu mulai.', 'Periksa waktu');
            return;
        }
        saving = true;
        var submit = form.querySelector('button[type="submit"]');
        submit.disabled = true;
        KK.api('api/contractions/update', { method: 'POST', body: {
            id: f.id.value, start_date: f.start_date.value, start_time: f.start_time.value,
            end_date: f.end_date.value, end_time: f.end_time.value, notes: f.notes.value
        } }).then(function (res) {
            editModal.hide();
            Swal.fire({ icon: 'success', title: 'Tersimpan', text: res.message, timer: 1400, showConfirmButton: false })
                .then(function () { location.reload(); });
        }).catch(function (err) {
            var msg = err.offline ? 'Tidak ada koneksi. Perubahan memerlukan koneksi internet.' : err.message;
            if (err.data && err.data.errors) {
                Object.keys(err.data.errors).forEach(function (k) {
                    var map = { started_at: 'start_time', ended_at: 'end_time', notes: 'notes' };
                    if (map[k] && f[map[k]]) f[map[k]].classList.add('is-invalid');
                });
            }
            KK.alertError(msg, 'Gagal menyimpan');
        }).finally(function () { saving = false; submit.disabled = false; });
    });
})();
