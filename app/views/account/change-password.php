<?php
use App\Core\Csrf;

$errors = $errors ?? [];
$inv = static fn (string $k) => isset($errors[$k]) ? ' is-invalid' : '';
$msg = static fn (string $k) => isset($errors[$k]) ? '<div class="invalid-feedback">' . e($errors[$k]) . '</div>' : '';
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <?php if (!empty($forced)): ?>
            <div class="alert alert-warning d-flex gap-2" role="alert" data-testid="must-change">
                <i class="bi bi-key-fill fs-5"></i>
                <div><strong>Password Anda direset oleh admin.</strong> Buat password baru sebelum melanjutkan. Gunakan password sementara yang Anda terima sebagai "password saat ini".</div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert" data-flash="success"><i class="bi bi-check-circle"></i> <?= e($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('ganti-password')) ?>" class="card" novalidate data-once>
            <div class="card-body">
                <?= Csrf::field() ?>
                <h2 class="h6 mb-3"><i class="bi bi-shield-lock"></i> Ganti password</h2>
                <div class="mb-3">
                    <label class="form-label" for="current_password">Password saat ini</label>
                    <input type="password" class="form-control form-control-lg<?= $inv('current_password') ?>" id="current_password" name="current_password" required autocomplete="current-password">
                    <?= $msg('current_password') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password baru</label>
                    <input type="password" class="form-control form-control-lg<?= $inv('password') ?>" id="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
                    <div class="form-text">Minimal 8 karakter.</div>
                    <?= $msg('password') ?>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password_confirmation">Ulangi password baru</label>
                    <input type="password" class="form-control form-control-lg<?= $inv('password_confirmation') ?>" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                    <?= $msg('password_confirmation') ?>
                </div>
                <button class="btn btn-primary rounded-pill px-4" type="submit"><i class="bi bi-check2"></i> Simpan Password</button>
                <?php if (empty($forced)): ?>
                    <a href="<?= e(url('profil')) ?>" class="btn btn-light rounded-pill ms-1">Kembali</a>
                <?php endif; ?>
                <p class="small text-muted mt-3 mb-0">Setelah password diganti, sesi login di perangkat lain akan berakhir.</p>
            </div>
        </form>
    </div>
</div>
