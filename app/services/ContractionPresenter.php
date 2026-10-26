<?php

declare(strict_types=1);

namespace App\Services;

/** Bentuk data kontraksi untuk respons API / view (waktu sudah dikonversi ke zona lokal). */
final class ContractionPresenter
{
    public static function toArray(array $row): array
    {
        return [
            'id'                 => $row['id'],
            'uuid'               => $row['uuid'],
            'session_id'         => $row['session_id'],
            'started_at_ms'      => TimeService::toEpochMs($row['started_at']),
            'ended_at_ms'        => TimeService::toEpochMs($row['ended_at']),
            'date'               => TimeService::localDate($row['started_at']),
            'date_iso'           => TimeService::localIsoDate($row['started_at']),
            'start_time'         => TimeService::localTime($row['started_at']),
            'end_date_iso'       => TimeService::localIsoDate($row['ended_at']),
            'end_time'           => $row['ended_at'] ? TimeService::localTime($row['ended_at']) : null,
            'duration_seconds'   => $row['duration_seconds'],
            'interval_seconds'   => $row['interval_seconds'],
            'duration_label'     => TimeService::formatDuration($row['duration_seconds']),
            'interval_label'     => TimeService::formatDuration($row['interval_seconds']),
            'notes'              => $row['notes'],
            'is_active'          => $row['ended_at'] === null,
        ];
    }
}
