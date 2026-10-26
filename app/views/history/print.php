<?php
use App\Services\TimeService as T;

$fmt = static fn (?int $v) => e(T::formatDuration($v));
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
            <div>Rentang: <?= e($filters['label']) ?></div>
        </div>
        <div class="text-end small">
            <div><strong>KontraksiKu</strong></div>
            <div>Dicetak: <?= e($printedAt) ?> WIB</div>
        </div>
    </header>

    <table class="summary">
        <tr>
            <td>Total kontraksi<br><strong><?= (int) $stats['total'] ?></strong></td>
            <td>Rata-rata durasi<br><strong><?= $fmt($stats['avg_duration']) ?></strong></td>
            <td>Rata-rata interval<br><strong><?= $fmt($stats['avg_interval']) ?></strong></td>
            <td>Durasi min – maks<br><strong><?= $fmt($stats['min_duration']) ?> – <?= $fmt($stats['max_duration']) ?></strong></td>
        </tr>
    </table>

    <?php if (!$rows): ?>
        <p>Tidak ada data kontraksi pada rentang ini.</p>
    <?php else: ?>
    <table class="data">
        <thead>
            <tr><th>No</th><th>Tanggal</th><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Interval</th><th>Catatan</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
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
    <?php endif; ?>

    <footer class="report-footer">
        Durasi = waktu selesai − waktu mulai. Interval = waktu mulai kontraksi − waktu mulai kontraksi sebelumnya (dalam sesi yang sama).
        Format durasi mm:dd (menit:detik). Laporan ini adalah catatan pribadi, bukan hasil pemeriksaan atau diagnosis medis.
    </footer>
</div>
