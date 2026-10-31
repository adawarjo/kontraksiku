<?php

declare(strict_types=1);

namespace App\Services;

/** Laporan cetak: kontraksi dikelompokkan per sesi beserta statistik & data grafik. */
final class ReportService
{
    /**
     * @param array $rows     kontraksi (urut waktu mulai ASC)
     * @param array $sessions sesi diindeks per id, dengan contraction_count (ContractionSession::findManyForUser)
     * @return array{stats: array, sessions: array}
     */
    public static function build(array $rows, array $sessions): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['session_id']][] = $row;
        }
        $result = [];
        foreach ($groups as $sessionId => $sessionRows) {
            $session = $sessions[$sessionId] ?? null;
            $active = ($session['status'] ?? null) === 'active';
            $result[] = [
                'id'     => $sessionId,
                'start'  => TimeService::localDateTime($session['started_at'] ?? $sessionRows[0]['started_at']),
                'end'    => $active || $session === null ? null : TimeService::localDateTime($session['ended_at']),
                'active' => $active,
                // Jumlah seluruh kontraksi sesi; bisa lebih besar dari jumlah baris bila laporan dibatasi rentang tanggal
                'total'  => (int) ($session['contraction_count'] ?? count($sessionRows)),
                'stats'  => self::stats($sessionRows),
                'chart'  => StatsService::chart($sessionRows),
                'rows'   => array_map([ContractionPresenter::class, 'toArray'], $sessionRows),
            ];
        }
        return ['stats' => self::stats($rows), 'sessions' => $result];
    }

    /** Jumlah, rata-rata durasi & interval, serta durasi min-maks (kontraksi selesai). */
    public static function stats(array $rows): array
    {
        $durations = array_column(array_filter($rows, static fn ($r) => $r['ended_at'] !== null), 'duration_seconds');
        return [
            'count'        => count($rows),
            'avg_duration' => StatsService::average($durations),
            'avg_interval' => StatsService::average(array_column($rows, 'interval_seconds')),
            'min_duration' => $durations ? min($durations) : null,
            'max_duration' => $durations ? max($durations) : null,
        ];
    }
}
