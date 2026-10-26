<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\ContractionSession;
use App\Models\Contraction;
use App\Services\StatsService;

final class DashboardController extends Controller
{
    public function index(): string
    {
        $state = (new StatsService())->dashboard($this->userId());
        return $this->view('dashboard/index', [
            'title' => 'Beranda',
            'nav'   => 'home',
            'user'  => Auth::user(),
            'state' => $state,
            'scripts' => ['vendor/chartjs/chart.umd.min.js', 'js/charts.js', 'js/timer.js'],
        ]);
    }

    /** Halaman grafik & analisis untuk sesi terpilih. */
    public function analysis(): string
    {
        $userId = $this->userId();
        $sessionsModel = new ContractionSession();
        $sessions = $sessionsModel->listForUser($userId, 30);
        $selectedId = filter_var($_GET['sesi'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $selected = $selectedId ? $sessionsModel->findForUser($selectedId, $userId) : null;
        if ($selected === null) {
            $selected = $sessions[0] ?? null;
        }
        $rows = $selected ? (new Contraction())->listBySession((int) $selected['id'], $userId) : [];
        $stats = new StatsService();

        return $this->view('dashboard/analysis', [
            'title'    => 'Grafik & Analisis',
            'nav'      => 'chart',
            'sessions' => $sessions,
            'selected' => $selected,
            'summary'  => StatsService::summarize($rows),
            'pattern'  => StatsService::pattern($rows),
            'chart'    => StatsService::chart($rows),
            'window30' => $stats->window($userId, 30),
            'window60' => $stats->window($userId, 60),
            'scripts'  => ['vendor/chartjs/chart.umd.min.js', 'js/charts.js', 'js/analysis.js'],
        ]);
    }
}
