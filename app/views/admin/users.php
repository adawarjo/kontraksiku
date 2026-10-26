<?php
use App\Core\Csrf;
use App\Services\TimeService as T;

$back = http_build_query(array_filter($filters + ['hal' => $page > 1 ? $page : null], static fn ($v) => $v !== null && $v !== ''));
$qs = static function (array $extra) use ($filters): string {
    $q = array_filter(array_merge($filters, $extra), static fn ($v) => $v !== null && $v !== '');
    return $q ? '?' . http_build_query($q) : '';
};
$actionLabels = [
    'create' => 'Buat akun', 'update' => 'Ubah akun', 'activate' => 'Aktifkan',
    'deactivate' => 'Nonaktifkan', 'reset_password' => 'Reset password', 'delete' => 'Hapus akun',
];
$hidden = static fn (int $id) => Csrf::field()
    . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="_back" value="' . e($back) . '">';
?>
<?php if ($flash): ?>
<script type="application/json" id="adminFlash"><?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>

<div class="alert alert-info small d-flex gap-2" role="note">
    <i class="bi bi-incognito fs-5"></i>
    <div>Admin dapat mengelola akun dan mereset password, tetapi <strong>tidak dapat melihat catatan kontraksi atau profil kehamilan</strong> pengguna lain. Setiap tindakan admin dicatat di log audit.</div>
</div>

<div class="row g-2 g-md-3 mb-3">
    <div class="col-4"><div class="stat-card"><div class="stat-label">Total pengguna</div><div class="stat-value" data-testid="count-total"><?= (int) $counts['total'] ?></div></div></div>
    <div class="col-4"><div class="stat-card"><div class="stat-label">Admin</div><div class="stat-value"><?= (int) $counts['admins'] ?></div></div></div>
    <div class="col-4"><div class="stat-card"><div class="stat-label">Nonaktif</div><div class="stat-value"><?= (int) $counts['inactive'] ?></div></div></div>
</div>

<form method="get" action="<?= e(url('admin')) ?>" class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label small mb-1">Cari nama / email</label>
                <input type="search" id="q" name="q" class="form-control" value="<?= e($filters['q']) ?>" maxlength="100" placeholder="mis. sari@">
            </div>
            <div class="col-6 col-md-2">
                <label for="peran" class="form-label small mb-1">Peran</label>
                <select id="peran" name="peran" class="form-select">
                    <option value="">Semua</option>
                    <option value="user" <?= $filters['peran'] === 'user' ? 'selected' : '' ?>>Pengguna</option>
                    <option value="admin" <?= $filters['peran'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label small mb-1">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="aktif" <?= $filters['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $filters['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary rounded-pill flex-grow-1" type="submit"><i class="bi bi-search"></i> Cari</button>
                <button class="btn btn-primary rounded-pill flex-grow-1" type="button" data-bs-toggle="modal" data-bs-target="#createModal" data-testid="add-user"><i class="bi bi-person-plus"></i> Tambah</button>
            </div>
        </div>
        <?php if (!$allowRegistration): ?>
            <p class="small text-muted mb-0 mt-2"><i class="bi bi-lock"></i> Registrasi mandiri ditutup (<code>APP_ALLOW_REGISTRATION=false</code>); akun baru hanya dapat dibuat oleh admin.</p>
        <?php endif; ?>
    </div>
</form>

<section class="card mb-3">
    <div class="card-body p-0 p-md-2">
        <?php if (!$rows): ?>
            <div class="empty-state"><i class="bi bi-people"></i><p class="mb-0">Tidak ada pengguna yang cocok.</p></div>
        <?php else: ?>
        <table class="table responsive-table user-table align-middle mb-0" data-testid="user-table">
            <thead>
                <tr><th>Pengguna</th><th>Peran</th><th>Status</th><th>Login terakhir</th><th>Terdaftar</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $u):
                $isMe = $u['id'] === $me['id'];
                $data = ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']];
            ?>
                <tr data-email="<?= e($u['email']) ?>">
                    <td data-label="Pengguna">
                        <div class="fw-semibold"><?= e($u['name']) ?> <?= $isMe ? '<span class="badge text-bg-light">Anda</span>' : '' ?></div>
                        <div class="small text-muted text-break"><?= e($u['email']) ?></div>
                    </td>
                    <td data-label="Peran">
                        <?= $u['role'] === 'admin'
                            ? '<span class="badge rounded-pill text-bg-primary bg-kk-rose"><i class="bi bi-shield-lock"></i> Admin</span>'
                            : '<span class="badge rounded-pill text-bg-light">Pengguna</span>' ?>
                    </td>
                    <td data-label="Status">
                        <?= $u['is_active']
                            ? '<span class="badge rounded-pill text-bg-success-subtle text-success-emphasis">Aktif</span>'
                            : '<span class="badge rounded-pill text-bg-secondary">Nonaktif</span>' ?>
                        <?= $u['must_change_password'] ? '<span class="badge rounded-pill text-bg-warning">Wajib ganti password</span>' : '' ?>
                    </td>
                    <td data-label="Login terakhir" class="small"><?= $u['last_login_at'] ? e(T::localDateTime($u['last_login_at'])) : '<span class="text-muted">Belum pernah</span>' ?></td>
                    <td data-label="Terdaftar" class="small"><?= e(T::localDate($u['created_at'])) ?></td>
                    <td data-label="Aksi" class="text-end">
                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                            <button type="button" class="btn btn-sm btn-light" data-edit-user="<?= e(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>" title="Ubah akun" aria-label="Ubah akun <?= e($u['email']) ?>"><i class="bi bi-pencil"></i></button>
                            <?php if (!$isMe): ?>
                                <form method="post" action="<?= e(url('admin/pengguna/reset-password')) ?>" data-confirm-title="Reset password?"
                                      data-confirm="Password <?= e($u['email']) ?> akan diganti dengan password sementara dan semua sesi loginnya diakhiri.">
                                    <?= $hidden($u['id']) ?>
                                    <button class="btn btn-sm btn-light" type="submit" title="Reset password" aria-label="Reset password <?= e($u['email']) ?>" data-action="reset"><i class="bi bi-key"></i></button>
                                </form>
                                <form method="post" action="<?= e(url('admin/pengguna/status')) ?>"
                                      data-confirm-title="<?= $u['is_active'] ? 'Nonaktifkan akun?' : 'Aktifkan akun?' ?>"
                                      data-confirm="<?= $u['is_active'] ? e($u['email']) . ' tidak akan bisa login dan semua sesinya diakhiri. Data tetap tersimpan.' : e($u['email']) . ' akan dapat login kembali.' ?>">
                                    <?= $hidden($u['id']) ?>
                                    <input type="hidden" name="active" value="<?= $u['is_active'] ? '0' : '1' ?>">
                                    <button class="btn btn-sm btn-light" type="submit" data-action="toggle" title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>" aria-label="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> <?= e($u['email']) ?>">
                                        <i class="bi <?= $u['is_active'] ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
                                    </button>
                                </form>
                                <form method="post" action="<?= e(url('admin/pengguna/hapus')) ?>" data-confirm-title="Hapus akun permanen?"
                                      data-confirm="Akun <?= e($u['email']) ?> beserta SELURUH catatan kontraksinya akan dihapus dan tidak dapat dikembalikan.">
                                    <?= $hidden($u['id']) ?>
                                    <button class="btn btn-sm btn-light text-danger" type="submit" data-action="delete" title="Hapus" aria-label="Hapus <?= e($u['email']) ?>"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php else: ?>
                                <a class="btn btn-sm btn-light" href="<?= e(url('ganti-password')) ?>" title="Ganti password Anda"><i class="bi bi-key"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</section>

<?php if ($pages > 1): ?>
<nav class="mb-3" aria-label="Halaman pengguna">
    <ul class="pagination justify-content-center flex-wrap">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('admin') . $qs(['hal' => $p])) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
    </ul>
    <p class="text-center small text-muted"><?= (int) $total ?> pengguna</p>
</nav>
<?php endif; ?>

<section class="card">
    <div class="card-body">
        <h2 class="h6"><i class="bi bi-journal-text"></i> Log audit terbaru</h2>
        <?php if (!$audit): ?>
            <p class="small text-muted mb-0">Belum ada tindakan admin.</p>
        <?php else: ?>
            <ul class="list-unstyled small mb-0 audit-list" data-testid="audit-log">
                <?php foreach ($audit as $log): ?>
                    <li>
                        <span class="text-muted"><?= e(T::localDateTime($log['created_at'])) ?></span> ·
                        <strong><?= e($actionLabels[$log['action']] ?? $log['action']) ?></strong>
                        <?= e($log['target_email'] ?? '') ?>
                        <span class="text-muted">oleh <?= e($log['admin_name'] ?? ($log['ip_address'] === 'cli' ? 'CLI' : 'admin terhapus')) ?></span>
                        <?php if ($log['details']): ?><div class="text-muted"><?= e($log['details']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<!-- Modal tambah pengguna -->
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= e(url('admin/pengguna/tambah')) ?>" data-once>
            <?= Csrf::field() ?>
            <input type="hidden" name="_back" value="<?= e($back) ?>">
            <div class="modal-header">
                <h2 class="modal-title h5" id="createTitle">Tambah Pengguna</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="newName">Nama</label><input class="form-control" id="newName" name="name" required maxlength="100"></div>
                <div class="mb-3"><label class="form-label" for="newEmail">Email</label><input type="email" class="form-control" id="newEmail" name="email" required maxlength="190"></div>
                <div class="mb-3">
                    <label class="form-label" for="newRole">Peran</label>
                    <select class="form-select" id="newRole" name="role"><option value="user">Pengguna</option><option value="admin">Admin</option></select>
                </div>
                <div class="mb-1">
                    <label class="form-label" for="newPassword">Password awal <span class="text-muted small">(opsional)</span></label>
                    <input type="text" class="form-control" id="newPassword" name="password" minlength="8" maxlength="72" autocomplete="off" placeholder="Kosongkan untuk dibuat otomatis">
                </div>
                <p class="small text-muted mb-0">Pengguna wajib mengganti password saat login pertama.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary rounded-pill"><i class="bi bi-person-plus"></i> Buat Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal ubah pengguna -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= e(url('admin/pengguna/ubah')) ?>" id="editUserForm" data-once>
            <?= Csrf::field() ?>
            <input type="hidden" name="_back" value="<?= e($back) ?>">
            <input type="hidden" name="id">
            <div class="modal-header">
                <h2 class="modal-title h5" id="editUserTitle">Ubah Akun</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="editName">Nama</label><input class="form-control" id="editName" name="name" required maxlength="100"></div>
                <div class="mb-3"><label class="form-label" for="editEmail">Email</label><input type="email" class="form-control" id="editEmail" name="email" required maxlength="190"></div>
                <div class="mb-1">
                    <label class="form-label" for="editRole">Peran</label>
                    <select class="form-select" id="editRole" name="role"><option value="user">Pengguna</option><option value="admin">Admin</option></select>
                </div>
                <p class="small text-muted mb-0" id="editRoleHint"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary rounded-pill"><i class="bi bi-check2"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>
<input type="hidden" id="currentUserId" value="<?= (int) $me['id'] ?>">
