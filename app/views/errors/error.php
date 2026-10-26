<div class="card auth-card text-center">
    <div class="card-body p-4">
        <div class="display-5 fw-bold text-primary-soft mb-2"><?= (int) $status ?></div>
        <p class="mb-4"><?= e($message) ?></p>
        <a href="<?= e(url('')) ?>" class="btn btn-primary rounded-pill px-4"><i class="bi bi-house"></i> Kembali ke Beranda</a>
    </div>
</div>
