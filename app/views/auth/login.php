<?php use App\Core\Csrf; ?>
<div class="card auth-card">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Masuk</h2>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2" role="alert"><i class="bi bi-check-circle"></i> <?= e($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2" role="alert" data-testid="login-error"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('login')) ?>" novalidate data-once>
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control form-control-lg" id="email" name="email" required autocomplete="email" inputmode="email"
                       value="<?= e($old['email'] ?? '') ?>" autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control form-control-lg" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill">
                <span class="btn-label"><i class="bi bi-box-arrow-in-right"></i> Masuk</span>
            </button>
        </form>
        <p class="text-center small text-muted mt-3 mb-0">Lupa password? Hubungi admin aplikasi untuk reset password.</p>
        <?php if ($allowRegistration): ?>
            <p class="text-center mt-2 mb-0">Belum punya akun? <a href="<?= e(url('daftar')) ?>">Daftar</a></p>
        <?php endif; ?>
    </div>
</div>
