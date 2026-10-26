<?php

declare(strict_types=1);

/*
 * Menjalankan seluruh pengujian PHP:
 *   php tests/run.php            -> uji layanan + uji HTTP
 *   php tests/run.php service    -> hanya uji layanan
 *   php tests/run.php http       -> hanya uji HTTP (menjalankan server bawaan PHP)
 */

require __DIR__ . '/TestCase.php';

$only = $argv[1] ?? null;
if ($only === null || $only === 'service') {
    require __DIR__ . '/ServiceTest.php';
    require __DIR__ . '/AdminServiceTest.php';
}
if ($only === null || $only === 'http') {
    require __DIR__ . '/HttpTest.php';
}

exit(T::summary());
