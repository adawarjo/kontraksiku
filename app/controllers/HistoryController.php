<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\Contraction;
use App\Models\ContractionSession;
use App\Models\PregnancyProfile;
use App\Services\ContractionPresenter;
use App\Services\ReportService;
use App\Services\TimeService;

final class HistoryController extends Controller
{
    private const SORTS = ['started_at' => 'Waktu mulai', 'duration' => 'Durasi', 'interval' => 'Interval'];

    public function index(): string
    {
        $userId = $this->userId();
        $filters = $this->filters();
        $perPage = (int) config('app.history_per_page', 15);
        $page = max(1, (int) filter_var(Request::query('hal', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));

        $model = new Contraction();
        $result = $model->history($userId, $filters['from_utc'], $filters['to_utc'], $filters['sort'], $filters['dir'], $perPage, ($page - 1) * $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));
        if ($page > $pages) {
            $page = $pages;
            $result = $model->history($userId, $filters['from_utc'], $filters['to_utc'], $filters['sort'], $filters['dir'], $perPage, ($page - 1) * $perPage);
        }

        if ($filters['errors']) {
            http_response_code(422);
        }

        return $this->view('history/index', [
            'title'    => 'Riwayat Kontraksi',
            'nav'      => 'history',
            'filters'  => $filters,
            'sorts'    => self::SORTS,
            'rows'     => array_map([ContractionPresenter::class, 'toArray'], $result['rows']),
            'total'    => $result['total'],
            'page'     => $page,
            'pages'    => $pages,
            'perPage'  => $perPage,
            'stats'    => $model->historyStats($userId, $filters['from_utc'], $filters['to_utc']),
            'query'    => $filters['query'],
            'scripts'  => ['js/history.js'],
        ]);
    }

    public function exportCsv(): void
    {
        $userId = $this->userId();
        $filters = $this->filters();
        if ($filters['errors']) {
            throw new HttpException(422, implode(' ', $filters['errors']));
        }
        $result = (new Contraction())->history($userId, $filters['from_utc'], $filters['to_utc'], $filters['sort'], $filters['dir'], 20000, 0);

        $filename = 'riwayat-kontraksi-' . (new \DateTimeImmutable('now', TimeService::local()))->format('d-m-Y-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        $headers = ['No', 'Tanggal', 'Waktu Mulai', 'Waktu Selesai', 'Durasi (mm:dd)', 'Durasi (detik)', 'Interval (mm:dd)', 'Interval (detik)', 'Catatan'];
        fputcsv($out, $headers, ';', '"', '');
        foreach ($result['rows'] as $i => $row) {
            $p = ContractionPresenter::toArray($row);
            fputcsv($out, [
                $i + 1,
                $p['date'],
                $p['start_time'],
                $p['end_time'] ?? 'Berlangsung',
                $p['duration_label'],
                $p['duration_seconds'] ?? '',
                $p['interval_label'],
                $p['interval_seconds'] ?? '',
                self::csvSafe((string) ($p['notes'] ?? '')),
            ], ';', '"', '');
        }
        fclose($out);
        exit;
    }

    /**
     * Laporan cetak dikelompokkan per sesi.
     * ?sesi=<id>|terakhir -> satu sesi (dari Beranda/Grafik); tanpa sesi -> filter tanggal Riwayat.
     */
    public function printReport(): string
    {
        $userId = $this->userId();
        $model = new Contraction();
        $sessionParam = (string) Request::query('sesi', '');
        if ($sessionParam !== '') {
            $session = $this->reportSession($userId, $sessionParam);
            $rows = $session ? $model->listBySession((int) $session['id'], $userId) : [];
            $scope = ['Sesi', $session
                ? TimeService::localDateTime($session['started_at']) . ' s.d. ' . ($session['ended_at'] ? TimeService::localDateTime($session['ended_at']) : 'sekarang')
                : 'Belum ada sesi pencatatan'];
        } else {
            $filters = $this->filters();
            if ($filters['errors']) {
                throw new HttpException(422, implode(' ', $filters['errors']));
            }
            $rows = $model->history($userId, $filters['from_utc'], $filters['to_utc'], 'started_at', 'asc', 5000, 0)['rows'];
            $scope = ['Rentang', $filters['label']];
        }
        $sessions = (new ContractionSession())->findManyForUser(array_column($rows, 'session_id'), $userId);

        return $this->view('history/print', [
            'title'     => 'Laporan Kontraksi',
            'user'      => Auth::user(),
            'profile'   => (new PregnancyProfile())->findByUser($userId),
            'scope'     => $scope,
            'report'    => ReportService::build($rows, $sessions),
            'printedAt' => (new \DateTimeImmutable('now', TimeService::local()))->format('d-m-Y H:i:s'),
            'scripts'   => ['vendor/chartjs/chart.umd.min.js', 'js/charts.js'],
        ], 'layouts/print');
    }

    /** Sesi untuk laporan: "terakhir" = sesi aktif/terbaru (null bila belum ada), atau ID milik pengguna. */
    private function reportSession(int $userId, string $param): ?array
    {
        $sessions = new ContractionSession();
        if ($param === 'terakhir') {
            return $sessions->findLatest($userId);
        }
        $id = filter_var($param, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $session = $id ? $sessions->findForUser($id, $userId) : null;
        if ($session === null) {
            throw new HttpException(404, 'Sesi tidak ditemukan.');
        }
        return $session;
    }

    /** Cegah formula injection saat CSV dibuka di spreadsheet. */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    /** Baca & validasi filter dari query string. */
    private function filters(): array
    {
        $errors = [];
        $rawFrom = (string) Request::query('dari', '');
        $rawTo = (string) Request::query('sampai', '');
        $rawDate = (string) Request::query('tanggal', '');

        $from = TimeService::parseLocalDate($rawFrom);
        $to = TimeService::parseLocalDate($rawTo);
        $date = TimeService::parseLocalDate($rawDate);

        if ($rawFrom !== '' && $from === null) {
            $errors['dari'] = 'Tanggal mulai tidak valid.';
        }
        if ($rawTo !== '' && $to === null) {
            $errors['sampai'] = 'Tanggal akhir tidak valid.';
        }
        if ($rawDate !== '' && $date === null) {
            $errors['tanggal'] = 'Tanggal pencarian tidak valid.';
        }
        if ($from && $to && $from > $to) {
            $errors['sampai'] = 'Tanggal akhir harus sama atau setelah tanggal mulai.';
        }
        if ($errors) {
            $from = $to = $date = null;
        }
        // Pencarian satu tanggal mengesampingkan rentang
        if ($date !== null) {
            $from = $to = $date;
        }
        [$fromUtc, $toUtc] = TimeService::localDateRangeToUtc($from, $to);

        $sort = (string) Request::query('urut', 'started_at');
        $sort = array_key_exists($sort, self::SORTS) ? $sort : 'started_at';
        $dir = Request::query('arah', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = array_filter([
            'dari'    => $errors ? '' : ($date ? '' : ($from?->format('Y-m-d') ?? '')),
            'sampai'  => $errors ? '' : ($date ? '' : ($to?->format('Y-m-d') ?? '')),
            'tanggal' => $errors ? '' : ($date?->format('Y-m-d') ?? ''),
            'urut'    => $sort,
            'arah'    => $dir,
        ], static fn ($v) => $v !== '');

        return [
            'from'     => $from,
            'to'       => $to,
            'date'     => $date,
            'from_utc' => $fromUtc,
            'to_utc'   => $toUtc,
            'sort'     => $sort,
            'dir'      => $dir,
            'errors'   => $errors,
            'raw'      => ['dari' => $rawFrom, 'sampai' => $rawTo, 'tanggal' => $rawDate],
            'query'    => $query,
            'label'    => self::rangeLabel($from, $to),
        ];
    }

    private static function rangeLabel(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): string
    {
        if (!$from && !$to) {
            return 'Semua tanggal';
        }
        if ($from && $to && $from == $to) {
            return 'Tanggal ' . $from->format('d-m-Y');
        }
        return ($from ? $from->format('d-m-Y') : 'awal') . ' s.d. ' . ($to ? $to->format('d-m-Y') : 'sekarang');
    }
}
