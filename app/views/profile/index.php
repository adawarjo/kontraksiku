<?php
use App\Core\Csrf;
use App\Services\TimeService;

$errors = $errors ?? [];
$p = $profile;
$val = static fn (string $k) => e($p[$k] ?? '');
$inv = static fn (string $k) => isset($errors[$k]) ? ' is-invalid' : '';
$msg = static fn (string $k) => isset($errors[$k]) ? '<div class="invalid-feedback">' . e($errors[$k]) . '</div>' : '';
$ga = TimeService::gestationalAge($p['estimated_due_date'] ?? null);
?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success" role="alert" data-flash="success"><i class="bi bi-check-circle"></i> <?= e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger" role="alert" data-flash="error"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <form method="post" action="<?= e(url('profil')) ?>" class="card" novalidate data-once>
            <div class="card-body">
                <?= Csrf::field() ?>
                <h2 class="h6 mb-1"><i class="bi bi-person-heart"></i> Profil kehamilan</h2>
                <p class="small text-muted">Semua isian selain nama akun bersifat opsional. Isi hanya yang Anda perlukan.</p>

                <div class="mb-3">
                    <label class="form-label" for="name">Nama akun</label>
                    <input class="form-control<?= $inv('name') ?>" id="name" name="name" maxlength="100" required value="<?= e($p['name'] ?? ($user['name'] ?? '')) ?>">
                    <?= $msg('name') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="nickname">Nama panggilan ibu <span class="text-muted small">(opsional)</span></label>
                    <input class="form-control<?= $inv('nickname') ?>" id="nickname" name="nickname" maxlength="60" value="<?= $val('nickname') ?>" placeholder="mis. Bunda Sari">
                    <?= $msg('nickname') ?>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="estimated_due_date">Hari perkiraan lahir (HPL)</label>
                        <input type="date" class="form-control<?= $inv('estimated_due_date') ?>" id="estimated_due_date" name="estimated_due_date" value="<?= $val('estimated_due_date') ?>">
                        <?= $msg('estimated_due_date') ?>
                        <?php if ($ga): ?><div class="form-text">Usia kehamilan saat ini: <?= $ga['weeks'] ?> minggu <?= $ga['days'] ?> hari</div><?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="tracking_start_date">Tanggal mulai pencatatan</label>
                        <input type="date" class="form-control<?= $inv('tracking_start_date') ?>" id="tracking_start_date" name="tracking_start_date" value="<?= $val('tracking_start_date') ?>">
                        <?= $msg('tracking_start_date') ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="notes">Catatan kehamilan <span class="text-muted small">(opsional)</span></label>
                    <textarea class="form-control<?= $inv('notes') ?>" id="notes" name="notes" rows="3" maxlength="2000" placeholder="mis. arahan bidan, riwayat persalinan sebelumnya"><?= $val('notes') ?></textarea>
                    <?= $msg('notes') ?>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="companion_name">Nama pendamping <span class="text-muted small">(opsional)</span></label>
                        <input class="form-control<?= $inv('companion_name') ?>" id="companion_name" name="companion_name" maxlength="100" value="<?= $val('companion_name') ?>">
                        <?= $msg('companion_name') ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="companion_contact">Kontak pendamping <span class="text-muted small">(opsional)</span></label>
                        <input type="tel" class="form-control<?= $inv('companion_contact') ?>" id="companion_contact" name="companion_contact" maxlength="30" value="<?= $val('companion_contact') ?>" inputmode="tel">
                        <?= $msg('companion_contact') ?>
                    </div>
                </div>
                <button class="btn btn-primary rounded-pill px-4" type="submit"><i class="bi bi-check2"></i> Simpan Profil</button>
            </div>
        </form>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-shield-check"></i> Keamanan akun</h2>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary btn-sm rounded-pill" href="<?= e(url('ganti-password')) ?>"><i class="bi bi-key"></i> Ganti password</a>
                    <?php if (App\Core\Auth::isAdmin()): ?>
                        <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= e(url('admin')) ?>"><i class="bi bi-shield-lock"></i> Manajemen pengguna</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($p['companion_contact'])): ?>
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-telephone"></i> Hubungi pendamping</h2>
                <a class="btn btn-outline-primary rounded-pill" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $p['companion_contact'])) ?>">
                    <i class="bi bi-telephone-outbound"></i> <?= e($p['companion_name'] ?: 'Pendamping') ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body small">
                <h2 class="h6"><i class="bi bi-phone"></i> Pasang di layar utama</h2>
                <p class="mb-2">KontraksiKu dapat dipasang seperti aplikasi (PWA). Di Chrome Android buka menu <i class="bi bi-three-dots-vertical"></i> lalu pilih <em>Instal aplikasi</em> / <em>Tambahkan ke layar utama</em>.</p>
                <button type="button" class="btn btn-sm btn-primary rounded-pill d-none" id="installButton"><i class="bi bi-download"></i> Pasang aplikasi</button>
                <p class="text-muted mb-0 mt-2">Instalasi PWA dan mode offline memerlukan HTTPS (atau <code>localhost</code>).</p>
            </div>
        </div>

        <div class="card border-danger-subtle">
            <div class="card-body small">
                <h2 class="h6 text-danger"><i class="bi bi-trash3"></i> Hapus akun &amp; semua data</h2>
                <p>Menghapus akun, profil, seluruh sesi, dan catatan kontraksi secara permanen.</p>
                <form method="post" action="<?= e(url('hapus-akun')) ?>" data-confirm="Akun beserta seluruh catatan kontraksi akan dihapus permanen dan tidak dapat dikembalikan." data-confirm-title="Hapus akun?">
                    <?= Csrf::field() ?>
                    <label class="form-label" for="delete_password">Masukkan password untuk konfirmasi</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="delete_password" name="password" required autocomplete="current-password">
                        <button class="btn btn-outline-danger" type="submit">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
