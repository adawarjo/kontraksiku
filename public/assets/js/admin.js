/* Halaman admin: notifikasi hasil aksi, password sementara, dan modal ubah akun. */
(function () {
    'use strict';
    if (!window.KK) return;

    // Hasil aksi (flash) -> SweetAlert2
    var flashEl = document.getElementById('adminFlash');
    if (flashEl) {
        var f;
        try { f = JSON.parse(flashEl.textContent); } catch (e) { f = null; }
        if (f) {
            if (f.password) {
                Swal.fire({
                    icon: 'success',
                    title: f.title,
                    html: '<p class="small">' + KK.escapeHtml(f.message) + '</p>'
                        + '<div class="temp-password" data-testid="temp-password">' + KK.escapeHtml(f.password) + '</div>'
                        + '<div class="small text-muted mt-2">Akun: ' + KK.escapeHtml(f.email) + '</div>'
                        + '<p class="small text-danger mt-2 mb-0"><i class="bi bi-eye-slash"></i> Password ini tidak dapat ditampilkan lagi setelah dialog ditutup.</p>',
                    showDenyButton: true,
                    denyButtonText: '<i class="bi bi-clipboard"></i> Salin',
                    confirmButtonText: 'Sudah dicatat',
                    allowOutsideClick: false,
                    preDeny: function () {
                        var done = function () { Swal.showValidationMessage('Password disalin ke clipboard.'); return false; };
                        if (navigator.clipboard && window.isSecureContext) {
                            return navigator.clipboard.writeText(f.password).then(done, function () {
                                Swal.showValidationMessage('Gagal menyalin, catat secara manual.');
                                return false;
                            });
                        }
                        var range = document.createRange();
                        range.selectNodeContents(Swal.getHtmlContainer().querySelector('.temp-password'));
                        var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
                        try { document.execCommand('copy'); return done(); } catch (e) { return false; }
                    }
                });
            } else {
                Swal.fire({
                    icon: f.type === 'error' ? 'error' : 'success',
                    title: f.title,
                    text: f.message,
                    confirmButtonText: 'Tutup'
                });
            }
        }
    }

    // Modal ubah akun
    var modalEl = document.getElementById('editUserModal');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);
    var form = document.getElementById('editUserForm');
    var me = document.getElementById('currentUserId').value;

    document.querySelectorAll('[data-edit-user]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var u = JSON.parse(btn.dataset.editUser);
            form.elements.id.value = u.id;
            form.elements.name.value = u.name;
            form.elements.email.value = u.email;
            form.elements.role.value = u.role;
            var self = String(u.id) === String(me);
            form.elements.role.disabled = self;
            document.getElementById('editRoleHint').textContent = self
                ? 'Anda tidak dapat mengubah peran akun Anda sendiri.'
                : 'Admin dapat mengelola akun lain, tetapi tidak dapat melihat catatan kontraksi.';
            // Field disabled tidak ikut terkirim -> sertakan nilai peran lewat input tersembunyi
            var hidden = form.querySelector('input[name="role"][type="hidden"]');
            if (self) {
                if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'role'; form.appendChild(hidden); }
                hidden.value = u.role;
            } else if (hidden) {
                hidden.remove();
            }
            modal.show();
        });
    });
})();
