<?php
$items = [
    'home'    => ['', 'bi-stopwatch', 'Timer'],
    'history' => ['riwayat', 'bi-clock-history', 'Riwayat'],
    'chart'   => ['grafik', 'bi-graph-up', 'Grafik'],
    'info'    => ['info', 'bi-heart-pulse', 'Info'],
    'profile' => ['profil', 'bi-person-heart', 'Profil'],
];
?>
<nav class="bottom-nav d-lg-none" aria-label="Navigasi bawah">
    <?php foreach ($items as $key => [$path, $icon, $label]): ?>
        <a href="<?= e(url($path)) ?>" class="<?= $nav === $key ? 'active' : '' ?>" <?= $nav === $key ? 'aria-current="page"' : '' ?>>
            <i class="bi <?= $icon ?>"></i>
            <span><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
