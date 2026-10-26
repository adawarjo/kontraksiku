<?php

declare(strict_types=1);

/* Helper E2E: jalankan COUNT(*) pada database uji. Contoh: php query.php "SELECT COUNT(*) FROM contractions" */
require dirname(__DIR__) . '/TestCase.php';

echo db_count($argv[1] ?? 'SELECT 0');
