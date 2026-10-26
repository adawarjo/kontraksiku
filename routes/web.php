<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HistoryController;
use App\Controllers\PageController;
use App\Controllers\ProfileController;
use App\Core\Router;

/** @var Router $router */

// Autentikasi
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/daftar', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/daftar', [AuthController::class, 'register'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

// Halaman (semua rute sengaja satu tingkat agar aset relatif tetap valid saat offline)
$router->get('/', [DashboardController::class, 'index'], ['auth']);
$router->get('/grafik', [DashboardController::class, 'analysis'], ['auth']);
$router->get('/riwayat', [HistoryController::class, 'index'], ['auth']);
$router->get('/ekspor-csv', [HistoryController::class, 'exportCsv'], ['auth']);
$router->get('/cetak-laporan', [HistoryController::class, 'printReport'], ['auth']);
$router->get('/profil', [ProfileController::class, 'show'], ['auth']);
$router->post('/profil', [ProfileController::class, 'save'], ['auth']);
$router->post('/hapus-akun', [ProfileController::class, 'deleteAccount'], ['auth']);
$router->get('/info', [PageController::class, 'info'], ['auth']);
$router->get('/ganti-password', [AccountController::class, 'showChangePassword'], ['auth']);
$router->post('/ganti-password', [AccountController::class, 'changePassword'], ['auth']);

// Admin: manajemen pengguna & reset password (tanpa akses ke data kontraksi pengguna)
$router->get('/admin', [AdminController::class, 'index'], ['auth', 'admin']);
$router->post('/admin/pengguna/tambah', [AdminController::class, 'store'], ['auth', 'admin']);
$router->post('/admin/pengguna/ubah', [AdminController::class, 'update'], ['auth', 'admin']);
$router->post('/admin/pengguna/status', [AdminController::class, 'toggleActive'], ['auth', 'admin']);
$router->post('/admin/pengguna/reset-password', [AdminController::class, 'resetPassword'], ['auth', 'admin']);
$router->post('/admin/pengguna/hapus', [AdminController::class, 'destroy'], ['auth', 'admin']);

// API JSON (POST otomatis diverifikasi CSRF lewat header X-CSRF-Token)
$router->get('/api/state', [ApiController::class, 'state'], ['auth']);
$router->get('/api/csrf', [ApiController::class, 'csrf'], ['auth']);
$router->post('/api/contractions/sync', [ApiController::class, 'sync'], ['auth']);
$router->post('/api/contractions/cancel', [ApiController::class, 'cancel'], ['auth']);
$router->post('/api/contractions/update', [ApiController::class, 'update'], ['auth']);
$router->post('/api/contractions/delete', [ApiController::class, 'delete'], ['auth']);
$router->post('/api/sessions/end', [ApiController::class, 'endSession'], ['auth']);
