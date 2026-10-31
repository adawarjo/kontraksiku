<?php
use App\Services\TimeService as T;

$fmt = static fn (?int $v) => e(T::formatDuration($v));
$stats = $report['stats'];
$sessions = $report['sessions'];
?>
<div class="report">
    <div class="no-print toolbar">
        <button type="button" class="btn btn-primary" data-print><i class="bi bi-printer"></i> Cetak / Simpan PDF</button>
        <button type="button" class="btn btn-light" data-close>Tutup</button>
    </div>

    <header class="report-header">
        <div>
            <h1>Laporan Kontraksi</h1>
            <div>Nama: <strong><?= e(($profile['nickname'] ?? null) ?: ($user['name'] ?? '-')) ?></strong></div>
            <?php if (!empty($profile['estimated_due_date'])): ?>
                <div>HPL: <?= e(date('d-m-Y', strtotime($profile['estimated_due_date']))) ?></div>
            <?php endif; ?>
            <div><?= e($scope[0]) ?>: <?= e($scope[1]) ?></div>
            <?php if (count($sessions) > 1): ?>
                <div>Jumlah sesi: <?= count($sessions) ?></div>
            <?php endif; ?>
        </div>
        <div class="text-end small">
            <div><strong>KontraksiKu</strong></div>
            <div>Dicetak: <?= e($printedAt) ?> WIB</div>
        </div>
    </header>

    <table class="summary">
        <tr>
            <td>Total kontraksi<br><strong><?= (int) $stats['count'] ?></strong></td>
            <td>Rata-rata durasi<br><strong><?= $fmt($stats['avg_duration']) ?></strong></td>
            <td>Rata-rata interval<br><strong><?= $fmt($stats['avg_interval']) ?></strong></td>
            <td>Durasi min – maks<br><strong><?= $fmt($stats['min_duration']) ?> – <?= $fmt($stats['max_duration']) ?></strong></td>
        </tr>
    </table>

    <?php if (!$sessions): ?>
        <p>Tidak ada data kontraksi pada <?= $scope[0] === 'Sesi' ? 'sesi ini' : 'rentang ini' ?>.</p>
    <?php endif; ?>

    <?php foreach ($sessions as $n => $s): ?>
    <section class="report-session" data-testid="report-session">
        <h2>
            Sesi <?= $n + 1 ?>
            <span class="session-time"><?= e($s['start']) ?> s.d. <?= $s['active'] ? 'sekarang (masih aktif)' : e($s['end']) ?></span>
        </h2>
        <?php if ($s['total'] > $s['stats']['count']): ?>
            <p class="session-note">Hanya kontraksi dalam rentang laporan: <?= (int) $s['stats']['count'] ?> dari <?= (int) $s['total'] ?> kontraksi sesi ini.</p>
        <?php endif; ?>
        <?php if (count($sessions) > 1): ?>
            <p class="session-stats">
                Jumlah: <strong><?= (int) $s['stats']['count'] ?></strong> ·
                Rata-rata durasi: <strong><?= $fmt($s['stats']['avg_duration']) ?></strong> ·
                Rata-rata interval: <strong><?= $fmt($s['stats']['avg_interval']) ?></strong> ·
                Durasi min – maks: <strong><?= $fmt($s['stats']['min_duration']) ?> – <?= $fmt($s['stats']['max_duration']) ?></strong>
            </p>
        <?php endif; ?>

        <?php if (count($s['chart']['labels']) >= 2): ?>
            <figure class="report-chart" data-report-chart>
                <script type="application/json"><?= json_encode($s['chart'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
                <canvas id="reportChart<?= (int) $s['id'] ?>" width="860" height="240" role="img" aria-label="Grafik durasi dan interval sesi <?= $n + 1 ?>"></canvas>
                <figcaption>Batang: durasi tiap kontraksi (sumbu kiri). Garis: interval dari kontraksi sebelumnya (sumbu kanan).</figcaption>
            </figure>
        <?php endif; ?>

        <table class="data">
            <thead>
                <tr><th>No</th><th>Tanggal</th><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Interval</th><th>Catatan</th></tr>
            </thead>
            <tbody>
            <?php foreach ($s['rows'] as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($r['date']) ?></td>
                    <td><?= e($r['start_time']) ?></td>
                    <td><?= $r['is_active'] ? 'Berlangsung' : e($r['end_time']) ?></td>
                    <td><?= e($r['duration_label']) ?></td>
                    <td><?= e($r['interval_label']) ?></td>
                    <td><?= e($r['notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endforeach; ?>

    <footer class="report-footer">
        Durasi = waktu selesai − waktu mulai. Interval = waktu mulai kontraksi − waktu mulai kontraksi sebelumnya (dalam sesi yang sama).
        Format durasi mm:dd (menit:detik). Laporan ini adalah catatan pribadi, bukan hasil pemeriksaan atau diagnosis medis.
    </footer>
</div>
