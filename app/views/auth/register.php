<?php
use App\Core\Csrf;

$errors = $errors ?? [];
$field = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? ' is-invalid' : '';
};
$msg = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? '<div class="invalid-feedback">' . e($errors[$name]) . '</div>' : '';
};
?>
<div class="card auth-card">
    <div class="card-body p-4">
        <h2 class="h5 mb-1">Buat Akun</h2>
        <p class="text-muted small mb-3">Hanya data yang diperlukan untuk login. Profil kehamilan bisa diisi nanti (opsional).</p>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('daftar')) ?>" novalidate data-once>
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label for="name" class="form-label">Nama</label>
                <input type="text" class="form-control form-control-lg<?= $field('name') ?>" id="name" name="name" required maxlength="100" autocomplete="name" value="<?= e($old['name'] ?? '') ?>">
                <?= $msg('name') ?>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control form-control-lg<?= $field('email') ?>" id="email" name="email" required maxlength="190" autocomplete="email" value="<?= e($old['email'] ?? '') ?>">
                <?= $msg('email') ?>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control form-control-lg<?= $field('password') ?>" id="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
                <div class="form-text">Minimal 8 karakter.</div>
                <?= $msg('password') ?>
            </div>
            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Ulangi Password</label>
                <input type="password" class="form-control form-control-lg<?= $field('password_confirmation') ?>" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                <?= $msg('password_confirmation') ?>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill"><i class="bi bi-person-plus"></i> Daftar</button>
        </form>
        <p class="text-center mt-3 mb-0">Sudah punya akun? <a href="<?= e(url('login')) ?>">Masuk</a></p>
    </div>
</div>
