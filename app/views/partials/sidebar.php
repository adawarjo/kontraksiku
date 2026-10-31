<?php
use App\Core\Csrf;

// Elemen ke-4 = buka di tab baru (laporan cetak)
$items = [
    'home'    => ['', 'bi-stopwatch', 'Beranda'],
    'history' => ['riwayat', 'bi-clock-history', 'Riwayat'],
    'chart'   => ['grafik', 'bi-graph-up', 'Grafik'],
    'report'  => ['cetak-laporan', 'bi-printer', 'Cetak Laporan', true],
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
        <?php foreach ($items as $key => $item): [$path, $icon, $label] = $item; ?>
            <a class="nav-link <?= $nav === $key ? 'active' : '' ?>" href="<?= e(url($path)) ?>" <?= $nav === $key ? 'aria-current="page"' : '' ?><?= !empty($item[3]) ? ' target="_blank" rel="noopener"' : '' ?>>
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
