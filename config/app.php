<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name'                 => Env::get('APP_NAME', 'KontraksiKu'),
    'env'                  => Env::get('APP_ENV', 'production'),
    'allow_registration'   => Env::bool('APP_ALLOW_REGISTRATION', true),
    'timezone'             => Env::get('APP_TIMEZONE', 'Asia/Jakarta'),
    'session_lifetime'     => (int) Env::get('SESSION_LIFETIME_MINUTES', '720'),
    'session_secure'       => Env::bool('SESSION_SECURE_COOKIE', false),
    'login_max_attempts'   => (int) Env::get('LOGIN_MAX_ATTEMPTS', '5'),
    'login_lock_minutes'   => (int) Env::get('LOGIN_LOCK_MINUTES', '15'),

    // Batas validasi data kontraksi
    'max_contraction_seconds' => 3 * 60 * 60,     // 3 jam: kontraksi lebih lama dianggap salah input
    'max_clock_skew_seconds'  => 5 * 60,          // toleransi jam perangkat lebih cepat dari server
    'max_sync_age_days'       => 14,              // data offline lebih lama dari ini ditolak
    'history_per_page'        => 15,
];
