<?php
use App\Services\TimeService as T;

/** @var array $state */
$s = $state['summary'];
$profile = $state['profile'];
$displayName = $profile['nickname'] ?: ($user['name'] ?? 'Bunda');
?>
<script type="application/json" id="initialState"><?= json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

<section class="greeting card border-0 mb-3">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <div class="text-muted small">Halo,</div>
            <div class="h4 mb-0 fw-semibold" data-testid="display-name"><?= e($displayName) ?></div>
        </div>
        <div class="text-end">
            <?php if ($profile['ga']): ?>
                <div class="small text-muted">Usia kehamilan</div>
                <div class="fw-semibold" data-testid="gestational-age"><?= (int) $profile['ga']['weeks'] ?> minggu <?= (int) $profile['ga']['days'] ?> hari</div>
            <?php else: ?>
                <a href="<?= e(url('profil')) ?>" class="small"><i class="bi bi-calendar-heart"></i> Isi HPL untuk melihat usia kehamilan</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="row g-3">
    <!-- Timer -->
    <div class="col-12 col-xl-5">
        <section class="card timer-card h-100" aria-labelledby="timerHeading">
            <div class="card-body text-center d-flex flex-column align-items-center">
                <h2 id="timerHeading" class="visually-hidden">Timer kontraksi</h2>
                <div class="session-status mb-2" id="sessionStatus" aria-live="polite">
                    <?php if ($state['session'] && $state['session']['is_active']): ?>
                        <span class="dot dot-on"></span> Sesi aktif sejak <?= e($state['session']['started_at']) ?>
                    <?php else: ?>
                        <span class="dot"></span> Belum ada sesi aktif. Tekan tombol untuk memulai.
                    <?php endif; ?>
                </div>

                <div class="timer-display" id="timerDisplay" role="timer" aria-live="off">00:00</div>
                <div class="timer-sub text-muted mb-3" id="timerSub">Siap mencatat</div>

                <button type="button" id="timerButton" class="timer-button is-idle" aria-pressed="false" data-testid="timer-button">
                    <i class="bi bi-play-fill" aria-hidden="true"></i>
                    <span class="label">Mulai Kontraksi</span>
                </button>

                <div class="timer-sync small mt-3" id="timerSync" aria-live="polite"></div>

                <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                    <button type="button" id="cancelButton" class="btn btn-outline-danger rounded-pill d-none">
                        <i class="bi bi-x-circle"></i> Batalkan kontraksi ini
                    </button>
                    <button type="button" id="endSessionButton" class="btn btn-outline-secondary rounded-pill <?= ($state['session']['is_active'] ?? false) ? '' : 'd-none' ?>">
                        <i class="bi bi-flag"></i> Tutup Sesi
                    </button>
                </div>
            </div>
        </section>
    </div>

    <!-- Statistik -->
    <div class="col-12 col-xl-7">
        <section aria-labelledby="statsHeading">
            <h2 id="statsHeading" class="section-title"><span id="statsScope"><?= ($state['session']['is_active'] ?? false) ? 'Sesi aktif' : 'Sesi terakhir' ?></span></h2>
            <div class="row g-2 g-md-3 stat-grid">
                <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-label">Jumlah kontraksi</div><div class="stat-value" data-stat="count"><?= (int) $s['count'] ?></div></div></div>
                <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-label">Durasi terakhir</div><div class="stat-value" data-stat="last_duration"><?= e(T::formatDuration($s['last_duration'])) ?></div></div></div>
                <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-label">Interval terakhir</div><div class="stat-value" data-stat="last_interval"><?= e(T::formatDuration($s['last_interval'])) ?></div></div></div>
                <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-label">Rata-rata durasi</div><div class="stat-value" data-stat="avg_duration"><?= e(T::formatDuration($s['avg_duration'])) ?></div></div></div>
                <div class="col-6 col-md-4"><div class="stat-card"><div class="stat-label">Rata-rata interval</div><div class="stat-value" data-stat="avg_interval"><?= e(T::formatDuration($s['avg_interval'])) ?></div></div></div>
                <div class="col-6 col-md-4"><div class="stat-card stat-accent"><div class="stat-label">Kontraksi 1 jam terakhir</div><div class="stat-value" data-stat="w60_count"><?= (int) $state['window60']['count'] ?></div></div></div>
            </div>
            <div class="card mt-3">
                <div class="card-body py-3">
                    <div class="fw-semibold mb-2"><i class="bi bi-hourglass-split"></i> Ringkasan 1 jam terakhir</div>
                    <p class="mb-0 small" id="windowSummary" data-testid="window-summary">
                        <?php App\Core\View::partial('window-summary', ['w' => $state['window60']]); ?>
                    </p>
                </div>
            </div>
        </section>
    </div>

    <!-- Antrean offline -->
    <div class="col-12 d-none" id="pendingSection">
        <section class="card border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                    <h2 class="h6 mb-0"><i class="bi bi-cloud-slash"></i> Tersimpan di perangkat, belum tersimpan di server</h2>
                    <button type="button" class="btn btn-sm btn-warning rounded-pill" data-sync-now><i class="bi bi-arrow-repeat"></i> Sinkronkan</button>
                </div>
                <ul class="list-group list-group-flush" id="pendingList"></ul>
            </div>
        </section>
    </div>

    <!-- Grafik -->
    <div class="col-12 col-xl-7">
        <section class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0"><i class="bi bi-bar-chart"></i> Durasi &amp; interval</h2>
                    <a href="<?= e(url('grafik')) ?>" class="small">Lihat analisis</a>
                </div>
                <div class="chart-box" id="dashboardChartBox">
                    <canvas id="dashboardChart" aria-label="Grafik durasi dan interval kontraksi" role="img"></canvas>
                </div>
                <div class="empty-state small d-none" id="dashboardChartEmpty">
                    <i class="bi bi-graph-up"></i>
                    <p class="mb-0">Grafik muncul setelah ada kontraksi yang selesai dicatat.</p>
                </div>
            </div>
        </section>
    </div>

    <!-- Riwayat terbaru -->
    <div class="col-12 col-xl-5">
        <section class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0"><i class="bi bi-clock-history"></i> Riwayat terbaru</h2>
                    <a href="<?= e(url('riwayat')) ?>" class="small">Semua riwayat</a>
                </div>
                <ul class="list-unstyled recent-list mb-0" id="recentList" data-testid="recent-list"></ul>
            </div>
        </section>
    </div>

    <div class="col-12">
        <aside class="safety-note">
            <i class="bi bi-shield-plus"></i>
            <div>
                <strong>Alat bantu pencatatan, bukan diagnosis.</strong>
                Ikuti arahan bidan/dokter. Segera cari pertolongan medis bila terjadi perdarahan, air ketuban pecah,
                gerakan janin berkurang, atau nyeri hebat terus-menerus. <a href="<?= e(url('info')) ?>">Baca selengkapnya</a>
            </div>
        </aside>
    </div>
</div>
