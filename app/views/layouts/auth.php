<?php /** @var string $content */ ?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title ?? '') ?> · <?= e(config('app.name')) ?></title>
    <meta name="theme-color" content="#c2577a">
    <meta name="app-base" content="<?= e(url('')) ?>">
    <link rel="manifest" href="<?= e(url('manifest.json')) ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= e(url('assets/icons/icon-192.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('assets/icons/icon-192.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body">
<main class="auth-wrap">
    <div class="auth-brand text-center mb-4">
        <img src="<?= e(url('assets/icons/icon-192.png')) ?>" alt="" width="72" height="72" class="mb-2">
        <h1 class="h3 mb-1">Kontraksi<strong>Ku</strong></h1>
        <p class="text-muted mb-0">Pencatat kontraksi untuk ibu &amp; pendamping</p>
    </div>
    <?= $content ?>
    <p class="text-center small text-muted mt-4 mb-0 px-3">
        <i class="bi bi-info-circle"></i> Aplikasi ini hanya alat bantu pencatatan, bukan alat diagnosis medis.
    </p>
</main>
<script src="<?= e(asset('js/pwa-register.js')) ?>"></script>
</body>
</html>
