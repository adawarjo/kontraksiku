<?php
use App\Core\Csrf;

$items = [
    'home'    => ['', 'bi-stopwatch', 'Beranda'],
    'history' => ['riwayat', 'bi-clock-history', 'Riwayat'],
    'chart'   => ['grafik', 'bi-graph-up', 'Grafik'],
    'info'    => ['info', 'bi-heart-pulse', 'Info Keselamatan'],
    'profile' => ['profil', 'bi-person-heart', 'Profil'],
];
if (App\Core\Auth::isAdmin()) {
    $items['admin'] = ['admin', 'bi-shield-lock', 'Manajemen Pengguna'];
}
?>
<aside class="app-sidebar d-none d-lg-flex" aria-label="Navigasi utama">
    <a href="<?= e(url('')) ?>" class="brand">
        <img src="<?= e(url('assets/icons/icon-192.png')) ?>" alt="" width="40" height="40">
        <span>Kontraksi<strong>Ku</strong></span>
    </a>
    <nav class="nav flex-column gap-1">
        <?php foreach ($items as $key => [$path, $icon, $label]): ?>
            <a class="nav-link <?= $nav === $key ? 'active' : '' ?>" href="<?= e(url($path)) ?>" <?= $nav === $key ? 'aria-current="page"' : '' ?>>
                <i class="bi <?= $icon ?>"></i> <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="mt-auto small">
        <div class="text-muted mb-2 text-truncate"><i class="bi bi-person-circle"></i> <?= e($currentUser['name'] ?? '') ?></div>
        <form method="post" action="<?= e(url('logout')) ?>" data-logout-form>
            <?= Csrf::field() ?>
            <button class="btn btn-outline-secondary btn-sm w-100 rounded-pill" type="submit"><i class="bi bi-box-arrow-right"></i> Keluar</button>
        </form>
    </div>
</aside>
