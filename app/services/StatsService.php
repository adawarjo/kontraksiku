<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contraction;
use App\Models\ContractionSession;
use App\Models\PregnancyProfile;

/**
 * Statistik deskriptif. Tidak ada diagnosis, prediksi persalinan, atau
 * kesimpulan medis — hanya angka dari catatan pengguna.
 */
final class StatsService
{
    private Contraction $contractions;
    private ContractionSession $sessions;

    public function __construct()
    {
        $this->contractions = new Contraction();
        $this->sessions = new ContractionSession();
    }

    /** Rata-rata dari daftar angka; null bila kosong. */
    public static function average(array $values): ?int
    {
        $values = array_values(array_filter($values, static fn ($v) => $v !== null));
        return $values ? (int) round(array_sum($values) / count($values)) : null;
    }

    /** Ringkasan untuk sekumpulan kontraksi (urut waktu mulai ASC). */
    public static function summarize(array $rows): array
    {
        $completed = array_values(array_filter($rows, static fn ($r) => $r['ended_at'] !== null));
        $last = $completed ? end($completed) : null;
        $lastAny = $rows ? end($rows) : null;
        return [
            'count'            => count($rows),
            'completed_count'  => count($completed),
            // Rata-rata durasi = total durasi kontraksi selesai / jumlah kontraksi selesai
            'avg_duration'     => self::average(array_column($completed, 'duration_seconds')),
            // Rata-rata interval = total interval valid / jumlah interval valid
            'avg_interval'     => self::average(array_column($rows, 'interval_seconds')),
            'last_duration'    => $last['duration_seconds'] ?? null,
            'last_interval'    => $lastAny['interval_seconds'] ?? null,
        ];
    }

    /** Statistik kontraksi yang dimulai dalam N menit terakhir. */
    public function window(int $userId, int $minutes): array
    {
        $since = TimeService::toDb(TimeService::now()->modify("-{$minutes} minutes"));
        $rows = $this->contractions->listStartedSince($userId, $since);
        // Interval hanya dihitung bila kontraksi sebelumnya juga berada dalam jendela
        $intervals = [];
        foreach ($rows as $i => $row) {
            if ($i > 0 && $row['interval_seconds'] !== null && $row['session_id'] === $rows[$i - 1]['session_id']) {
                $intervals[] = $row['interval_seconds'];
            }
        }
        $completed = array_filter($rows, static fn ($r) => $r['ended_at'] !== null);
        return [
            'minutes'      => $minutes,
            'count'        => count($rows),
            'avg_duration' => self::average(array_column($completed, 'duration_seconds')),
            'avg_interval' => self::average($intervals),
        ];
    }

    /**
     * Perbandingan paruh awal vs paruh akhir sesi (deskriptif, tanpa kesimpulan medis).
     */
    public static function pattern(array $rows): ?array
    {
        $completed = array_values(array_filter($rows, static fn ($r) => $r['ended_at'] !== null));
        if (count($completed) < 4) {
            return null;
        }
        $half = intdiv(count($completed), 2);
        $first = array_slice($completed, 0, $half);
        $second = array_slice($completed, $half);
        $result = [
            'first_avg_duration'  => self::average(array_column($first, 'duration_seconds')),
            'second_avg_duration' => self::average(array_column($second, 'duration_seconds')),
            'first_avg_interval'  => self::average(array_column($first, 'interval_seconds')),
            'second_avg_interval' => self::average(array_column($second, 'interval_seconds')),
            'first_count'         => count($first),
            'second_count'        => count($second),
        ];
        $result['text'] = self::patternText($result);
        return $result;
    }

    private static function patternText(array $p): string
    {
        $parts = [];
        $describe = static function (?int $a, ?int $b, string $label): ?string {
            if ($a === null || $b === null) {
                return null;
            }
            $diff = $b - $a;
            if (abs($diff) < 5) {
                return sprintf('%s relatif sama (sekitar %s).', $label, TimeService::formatDuration($b));
            }
            return sprintf(
                '%s %s dari %s menjadi %s.',
                $label,
                $diff > 0 ? 'bertambah' : 'berkurang',
                TimeService::formatDuration($a),
                TimeService::formatDuration($b)
            );
        };
        $parts[] = $describe($p['first_avg_duration'], $p['second_avg_duration'], 'Rata-rata durasi');
        $parts[] = $describe($p['first_avg_interval'], $p['second_avg_interval'], 'Rata-rata interval');
        $parts = array_filter($parts);
        return 'Dibandingkan paruh awal sesi, pada paruh akhir: ' . implode(' ', $parts)
            . ' Ringkasan ini hanya menggambarkan catatan Anda, bukan penilaian medis.';
    }

    /** Data grafik untuk sekumpulan kontraksi (maks. 100 terakhir). */
    public static function chart(array $rows): array
    {
        $rows = array_slice(array_values(array_filter($rows, static fn ($r) => $r['ended_at'] !== null)), -100);
        return [
            'labels'    => array_map(static fn ($r) => TimeService::localTime($r['started_at']), $rows),
            'dates'     => array_map(static fn ($r) => TimeService::localDate($r['started_at']), $rows),
            'durations' => array_map(static fn ($r) => $r['duration_seconds'], $rows),
            'intervals' => array_map(static fn ($r) => $r['interval_seconds'], $rows),
        ];
    }

    /** Seluruh data untuk dashboard / endpoint /api/state. */
    public function dashboard(int $userId): array
    {
        $session = $this->sessions->findActive($userId);
        $sessionIsActive = $session !== null;
        if ($session === null) {
            $session = $this->sessions->findLatest($userId);
        }
        $rows = $session ? $this->contractions->listBySession((int) $session['id'], $userId) : [];
        $active = $this->contractions->findActive($userId);
        $profile = (new PregnancyProfile())->findByUser($userId);

        return [
            'server_time_ms' => TimeService::now()->getTimestamp() * 1000,
            'active'         => $active ? ContractionPresenter::toArray($active) : null,
            'session'        => $session ? [
                'id'          => (int) $session['id'],
                'is_active'   => $sessionIsActive,
                'started_at'  => TimeService::localDateTime($session['started_at']),
                'ended_at'    => $session['ended_at'] ? TimeService::localDateTime($session['ended_at']) : null,
            ] : null,
            'summary'        => self::summarize($rows),
            'window30'       => $this->window($userId, 30),
            'window60'       => $this->window($userId, 60),
            'pattern'        => self::pattern($rows),
            'chart'          => self::chart($rows),
            'recent'         => array_map([ContractionPresenter::class, 'toArray'], $this->contractions->recent($userId, 5)),
            'profile'        => [
                'nickname' => $profile['nickname'] ?? null,
                'ga'       => TimeService::gestationalAge($profile['estimated_due_date'] ?? null),
            ],
        ];
    }
}
