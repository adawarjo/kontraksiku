<?php

declare(strict_types=1);

/* Siapkan database uji untuk E2E: reset + satu akun. */
require dirname(__DIR__) . '/TestCase.php';

reset_test_database();
make_user('e2e@contoh.test', 'rahasia123');
(new App\Models\User())->create('Admin E2E', 'admin@contoh.test', password_hash('rahasia123', PASSWORD_DEFAULT), 'admin');
echo "ok\n";
