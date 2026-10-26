<?php
use App\Services\TimeService as T;

$fmt = static fn (?int $v) => e(T::formatDuration($v));
$qs = static function (array $extra = []) use ($query): string {
    $q = array_filter(array_merge($query, $extra), static fn ($v) => $v !== null && $v !== '');
    return $q ? '?' . http_build_query($q) : '';
};
$errors = $filters['errors'];
$sortLink = static function (string $key, string $label) use ($filters, $qs): string {
    $active = $filters['sort'] === $key;
    $dir = $active && $filters['dir'] === 'desc' ? 'asc' : 'desc';
    $icon = $active ? ($filters['dir'] === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up';
    return '<a class="sort-link' . ($active ? ' active' : '') . '" href="' . e(url('riwayat') . $qs(['urut' => $key, 'arah' => $dir, 'hal' => null])) . '">'
        . e($label) . ' <i class="bi ' . $icon . '"></i></a>';
};
$startNo = ($page - 1) * $perPage;
?>
<form method="get" action="<?= e(url('riwayat')) ?>" class="card mb-3 no-print" novalidate>
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="dari" class="form-label small mb-1">Dari tanggal</label>
                <input type="date" id="dari" name="dari" class="form-control<?= isset($errors['dari']) ? ' is-invalid' : '' ?>" value="<?= e($filters['raw']['dari']) ?>">
                <?php if (isset($errors['dari'])): ?><div class="invalid-feedback"><?= e($errors['dari']) ?></div><?php endif; ?>
            </div>
            <div class="col-6 col-md-3">
                <label for="sampai" class="form-label small mb-1">Sampai tanggal</label>
                <input type="date" id="sampai" name="sampai" class="form-control<?= isset($errors['sampai']) ? ' is-invalid' : '' ?>" value="<?= e($filters['raw']['sampai']) ?>">
                <?php if (isset($errors['sampai'])): ?><div class="invalid-feedback"><?= e($errors['sampai']) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-3">
                <label for="tanggal" class="form-label small mb-1">Cari tanggal tertentu</label>
                <input type="date" id="tanggal" name="tanggal" class="form-control<?= isset($errors['tanggal']) ? ' is-invalid' : '' ?>" value="<?= e($filters['raw']['tanggal']) ?>">
                <?php if (isset($errors['tanggal'])): ?><div class="invalid-feedback"><?= e($errors['tanggal']) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <input type="hidden" name="urut" value="<?= e($filters['sort']) ?>">
                <input type="hidden" name="arah" value="<?= e($filters['dir']) ?>">
                <button class="btn btn-primary flex-grow-1 rounded-pill" type="submit"><i class="bi bi-funnel"></i> Terapkan</button>
                <a class="btn btn-light rounded-pill" href="<?= e(url('riwayat')) ?>" title="Reset filter" aria-label="Reset filter"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </div>
</form>

<?php if ($errors): ?>
    <div class="alert alert-danger small" role="alert" data-testid="filter-error"><i class="bi bi-exclamation-triangle"></i> <?= e(implode(' ', $errors)) ?> Filter tidak diterapkan.</div>
<?php endif; ?>

<section class="mb-3" aria-labelledby="rangeStats">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 id="rangeStats" class="section-title mb-0">Ringkasan: <span data-testid="range-label"><?= e($filters['label']) ?></span></h2>
        <div class="d-flex gap-2 no-print">
            <a class="btn btn-outline-primary btn-sm rounded-pill" href="<?= e(url('ekspor-csv') . $qs()) ?>" data-testid="export-csv"><i class="bi bi-filetype-csv"></i> Ekspor CSV</a>
            <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= e(url('cetak-laporan') . $qs()) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Cetak</a>
        </div>
    </div>
    <div class="row g-2">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Total kontraksi</div><div class="stat-value" data-testid="stat-total"><?= (int) $stats['total'] ?></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Rata-rata durasi</div><div class="stat-value"><?= $fmt($stats['avg_duration']) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Rata-rata interval</div><div class="stat-value"><?= $fmt($stats['avg_interval']) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Durasi min – maks</div><div class="stat-value fs-6"><?= $fmt($stats['min_duration']) ?> – <?= $fmt($stats['max_duration']) ?></div></div></div>
    </div>
</section>

<section class="card">
    <div class="card-body p-0 p-md-2">
        <?php if (!$rows): ?>
            <div class="empty-state" data-testid="history-empty">
                <i class="bi bi-inbox"></i>
                <p class="mb-2">Belum ada data kontraksi<?= $filters['from'] || $filters['to'] ? ' pada rentang ini' : '' ?>.</p>
                <a href="<?= e(url('')) ?>" class="btn btn-primary btn-sm rounded-pill">Ke timer</a>
            </div>
        <?php else: ?>
            <table class="table history-table align-middle mb-0" data-testid="history-table">
                <thead>
                    <tr>
                        <th scope="col">No</th>
                        <th scope="col"><?= $sortLink('started_at', 'Tanggal') ?></th>
                        <th scope="col">Mulai</th>
                        <th scope="col">Selesai</th>
                        <th scope="col"><?= $sortLink('duration', 'Durasi') ?></th>
                        <th scope="col"><?= $sortLink('interval', 'Interval') ?></th>
                        <th scope="col">Catatan</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr data-row data-contraction="<?= e(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>">
                        <td data-label="No"><?= $startNo + $i + 1 ?></td>
                        <td data-label="Tanggal" class="fw-semibold"><?= e($r['date']) ?></td>
                        <td data-label="Mulai"><?= e($r['start_time']) ?></td>
                        <td data-label="Selesai"><?= $r['is_active'] ? '<span class="badge text-bg-warning">Berlangsung</span>' : e($r['end_time']) ?></td>
                        <td data-label="Durasi"><span class="mono"><?= e($r['duration_label']) ?></span></td>
                        <td data-label="Interval"><span class="mono"><?= e($r['interval_label']) ?></span></td>
                        <td data-label="Catatan" class="notes-cell"><?= $r['notes'] !== null ? e($r['notes']) : '<span class="text-muted">-</span>' ?></td>
                        <td data-label="Aksi" class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-light" data-action="detail" aria-label="Detail"><i class="bi bi-eye"></i></button>
                            <button type="button" class="btn btn-sm btn-light" data-action="edit" aria-label="Edit" <?= $r['is_active'] ? 'disabled title="Akhiri kontraksi terlebih dahulu"' : '' ?>><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-sm btn-light text-danger" data-action="delete" aria-label="Hapus"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<?php if ($pages > 1): ?>
<nav class="mt-3" aria-label="Halaman riwayat">
    <ul class="pagination justify-content-center flex-wrap">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(url('riwayat') . $qs(['hal' => max(1, $page - 1)])) ?>" aria-label="Sebelumnya">&laquo;</a></li>
        <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('riwayat') . $qs(['hal' => $p])) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(url('riwayat') . $qs(['hal' => min($pages, $page + 1)])) ?>" aria-label="Berikutnya">&raquo;</a></li>
    </ul>
    <p class="text-center small text-muted">Halaman <?= $page ?> dari <?= $pages ?> · <?= (int) $total ?> data</p>
</nav>
<?php endif; ?>

<!-- Modal detail -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="detailTitle">Detail Kontraksi</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body"><dl class="row mb-0" id="detailBody"></dl></div>
        </div>
    </div>
</div>

<!-- Modal edit -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="editForm" novalidate>
            <div class="modal-header">
                <h2 class="modal-title h5" id="editTitle">Edit Kontraksi</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id">
                <div class="row g-2">
                    <div class="col-7"><label class="form-label small" for="editStartDate">Tanggal mulai</label><input type="date" class="form-control" id="editStartDate" name="start_date" required></div>
                    <div class="col-5"><label class="form-label small" for="editStartTime">Jam mulai</label><input type="time" step="1" class="form-control" id="editStartTime" name="start_time" required></div>
                    <div class="col-7"><label class="form-label small" for="editEndDate">Tanggal selesai</label><input type="date" class="form-control" id="editEndDate" name="end_date" required></div>
                    <div class="col-5"><label class="form-label small" for="editEndTime">Jam selesai</label><input type="time" step="1" class="form-control" id="editEndTime" name="end_time" required></div>
                    <div class="col-12"><label class="form-label small" for="editNotes">Catatan (opsional)</label><textarea class="form-control" id="editNotes" name="notes" rows="3" maxlength="500"></textarea></div>
                </div>
                <p class="small text-muted mt-2 mb-0">Waktu dalam zona Asia/Jakarta (WIB). Interval dihitung ulang otomatis.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary rounded-pill"><i class="bi bi-check2"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>
