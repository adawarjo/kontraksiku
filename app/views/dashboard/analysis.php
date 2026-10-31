<?php
use App\Services\TimeService as T;

$fmt = static fn (?int $v) => e(T::formatDuration($v));
?>
<script type="application/json" id="analysisData"><?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

<div class="alert alert-info d-flex gap-2 align-items-start small" role="note">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div>Grafik dan ringkasan di halaman ini hanya menampilkan catatan Anda. Aplikasi tidak membuat diagnosis,
        tidak memprediksi waktu persalinan, dan tidak menentukan apakah persalinan sudah dimulai.
        Konsultasikan pola kontraksi dengan bidan atau dokter.</div>
</div>

<?php if (!$sessions): ?>
    <div class="card"><div class="card-body empty-state">
        <i class="bi bi-graph-up"></i>
        <p class="mb-2">Belum ada sesi pencatatan.</p>
        <a href="<?= e(url('')) ?>" class="btn btn-primary rounded-pill">Mulai mencatat</a>
    </div></div>
<?php else: ?>

<form method="get" action="<?= e(url('grafik')) ?>" class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
        <div class="flex-grow-1">
            <label for="sesi" class="form-label small mb-1">Pilih sesi pencatatan</label>
            <select id="sesi" name="sesi" class="form-select" data-autosubmit>
                <?php foreach ($sessions as $sess): ?>
                    <option value="<?= (int) $sess['id'] ?>" <?= (int) $sess['id'] === (int) ($selected['id'] ?? 0) ? 'selected' : '' ?>>
                        <?= e(T::localDateTime($sess['started_at'])) ?>
                        <?= $sess['status'] === 'active' ? '(aktif)' : '– ' . e(T::localDateTime($sess['ended_at'])) ?>
                        · <?= (int) $sess['contraction_count'] ?> kontraksi
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button class="btn btn-primary">Tampilkan</button></noscript>
        <?php if ($selected): ?>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= e(url('cetak-laporan?sesi=' . (int) $selected['id'])) ?>" target="_blank" rel="noopener" data-testid="print-session">
                <i class="bi bi-printer"></i> Cetak laporan sesi
            </a>
        <?php endif; ?>
    </div>
</form>

<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Kontraksi di sesi</div><div class="stat-value"><?= (int) $summary['count'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Rata-rata durasi</div><div class="stat-value"><?= $fmt($summary['avg_duration']) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Rata-rata interval</div><div class="stat-value"><?= $fmt($summary['avg_interval']) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Durasi terakhir</div><div class="stat-value"><?= $fmt($summary['last_duration']) ?></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><i class="bi bi-bar-chart"></i> Durasi setiap kontraksi</h2>
            <div class="chart-box"><canvas id="durationChart" role="img" aria-label="Grafik durasi setiap kontraksi"></canvas></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><i class="bi bi-activity"></i> Interval antarkontraksi</h2>
            <div class="chart-box"><canvas id="intervalChart" role="img" aria-label="Grafik interval antarkontraksi"></canvas></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><i class="bi bi-arrow-left-right"></i> Perubahan pola selama sesi</h2>
            <?php if ($pattern): ?>
                <table class="table table-sm mb-2 align-middle">
                    <thead><tr><th></th><th>Paruh awal</th><th>Paruh akhir</th></tr></thead>
                    <tbody>
                        <tr><td>Jumlah</td><td><?= (int) $pattern['first_count'] ?></td><td><?= (int) $pattern['second_count'] ?></td></tr>
                        <tr><td>Rata-rata durasi</td><td><?= $fmt($pattern['first_avg_duration']) ?></td><td><?= $fmt($pattern['second_avg_duration']) ?></td></tr>
                        <tr><td>Rata-rata interval</td><td><?= $fmt($pattern['first_avg_interval']) ?></td><td><?= $fmt($pattern['second_avg_interval']) ?></td></tr>
                    </tbody>
                </table>
                <p class="small text-muted mb-0" data-testid="pattern-text"><?= e($pattern['text']) ?></p>
            <?php else: ?>
                <p class="text-muted small mb-0">Ringkasan perubahan pola tersedia setelah minimal 4 kontraksi selesai dicatat dalam sesi.</p>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><i class="bi bi-hourglass-split"></i> Statistik waktu terakhir</h2>
            <div class="row g-2">
                <?php foreach ([$window30, $window60] as $w): ?>
                    <div class="col-6">
                        <div class="window-box">
                            <div class="fw-semibold mb-1"><?= (int) $w['minutes'] ?> menit terakhir</div>
                            <div class="small">Jumlah: <strong><?= (int) $w['count'] ?></strong></div>
                            <div class="small">Rata-rata durasi: <strong><?= $fmt($w['avg_duration']) ?></strong></div>
                            <div class="small">Rata-rata interval: <strong><?= $fmt($w['avg_interval']) ?></strong></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div></div>
    </div>
</div>
<?php endif; ?>
