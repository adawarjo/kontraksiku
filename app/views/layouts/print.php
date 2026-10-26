<?php /** @var string $content */ ?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Laporan') ?> · <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>">
</head>
<body class="print-body">
<?= $content ?>
<script src="<?= e(asset('js/print.js')) ?>"></script>
</body>
</html>
