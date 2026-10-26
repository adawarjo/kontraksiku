<?php
use App\Services\TimeService as T;

/** @var array $w */
if ($w['count'] === 0): ?>
    Belum ada kontraksi yang dicatat dalam <?= (int) $w['minutes'] ?> menit terakhir.
<?php else: ?>
    <?= (int) $w['count'] ?> kontraksi dalam <?= (int) $w['minutes'] ?> menit terakhir ·
    rata-rata durasi <strong><?= e(T::formatDuration($w['avg_duration'])) ?></strong> ·
    rata-rata interval <strong><?= e(T::formatDuration($w['avg_interval'])) ?></strong>
<?php endif;
