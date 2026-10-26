<?php
use App\Core\Auth;
use App\Core\Csrf;

/** @var string $content */
$nav = $nav ?? '';
$scripts = $scripts ?? [];
$currentUser = Auth::user();
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title ?? 'Beranda') ?> · <?= e(config('app.name')) ?></title>
    <meta name="theme-color" content="#c2577a">
    <meta name="app-base" content="<?= e(url('')) ?>">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="app-user" content="<?= e((string) ($currentUser['id'] ?? '')) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="KontraksiKu">
    <link rel="manifest" href="<?= e(url('manifest.json')) ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= e(url('assets/icons/icon-192.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('assets/icons/icon-192.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/sweetalert2/sweetalert2.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-body">
<a class="visually-hidden-focusable skip-link" href="#main">Lewati ke konten</a>

<div class="app-shell">
    <?php App\Core\View::partial('sidebar', ['nav' => $nav, 'currentUser' => $currentUser]); ?>

    <div class="app-main">
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-2">
                <img src="<?= e(url('assets/icons/icon-192.png')) ?>" alt="" width="32" height="32" class="rounded-3 d-lg-none">
                <h1 class="app-title mb-0"><?= e($title ?? '') ?></h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span id="connectionBadge" class="badge rounded-pill text-bg-success-subtle text-success-emphasis" role="status" aria-live="polite">
                    <i class="bi bi-wifi"></i> <span class="label">Online</span>
                </span>
                <span id="pendingBadge" class="badge rounded-pill text-bg-warning d-none" role="status" aria-live="polite" title="Data di perangkat yang belum tersimpan di server">
                    <i class="bi bi-cloud-arrow-up"></i> <span class="count">0</span> belum tersinkron
                </span>
                <?php if (Auth::isAdmin()): ?>
                    <a href="<?= e(url('admin')) ?>" class="btn btn-sm btn-light rounded-pill d-lg-none" aria-label="Manajemen pengguna" title="Manajemen pengguna"><i class="bi bi-shield-lock"></i></a>
                <?php endif; ?>
                <form method="post" action="<?= e(url('logout')) ?>" class="d-lg-none" data-logout-form>
                    <?= Csrf::field() ?>
                    <button class="btn btn-sm btn-light rounded-pill" type="submit" aria-label="Keluar"><i class="bi bi-box-arrow-right"></i></button>
                </form>
            </div>
        </header>

        <main id="main" class="app-content">
            <?= $content ?>
        </main>
    </div>
</div>

<?php App\Core\View::partial('bottom-nav', ['nav' => $nav]); ?>

<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.min.js')) ?>"></script>
<script src="<?= e(asset('js/queue.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
<script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
