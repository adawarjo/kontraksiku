<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Konversi waktu. Aturan:
 * - Database: DATETIME dalam UTC ("Y-m-d H:i:s").
 * - Perangkat: epoch milidetik (tidak bergantung zona waktu).
 * - Tampilan: Asia/Jakarta, tanggal DD-MM-YYYY, waktu HH:mm:ss.
 */
final class TimeService
{
    public const DB_FORMAT = 'Y-m-d H:i:s';

    public static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }

    public static function local(): DateTimeZone
    {
        return new DateTimeZone((string) config('app.timezone', 'Asia/Jakarta'));
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::utc());
    }

    /** Epoch milidetik dari perangkat -> waktu UTC (dibulatkan ke detik). Null bila tidak valid. */
    public static function fromEpochMs(mixed $value): ?DateTimeImmutable
    {
        if (is_string($value) && preg_match('/^\d{10,14}$/', $value)) {
            $value = (int) $value;
        }
        if (!is_int($value) && !(is_float($value) && is_finite($value))) {
            return null;
        }
        $seconds = intdiv((int) $value, 1000);
        // Rentang wajar: 2020-01-01 s.d. 2100-01-01
        if ($seconds < 1577836800 || $seconds > 4102444800) {
            return null;
        }
        return (new DateTimeImmutable('@' . $seconds))->setTimezone(self::utc());
    }

    public static function toDb(DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(self::utc())->format(self::DB_FORMAT);
    }

    public static function fromDb(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, self::utc());
    }

    public static function toEpochMs(?string $db): ?int
    {
        return $db === null ? null : self::fromDb($db)->getTimestamp() * 1000;
    }

    public static function diffSeconds(string $fromDb, string $toDb): int
    {
        return self::fromDb($toDb)->getTimestamp() - self::fromDb($fromDb)->getTimestamp();
    }

    public static function localDate(?string $db): string
    {
        return $db === null ? '-' : self::fromDb($db)->setTimezone(self::local())->format('d-m-Y');
    }

    public static function localTime(?string $db): string
    {
        return $db === null ? '-' : self::fromDb($db)->setTimezone(self::local())->format('H:i:s');
    }

    public static function localDateTime(?string $db): string
    {
        return $db === null ? '-' : self::fromDb($db)->setTimezone(self::local())->format('d-m-Y H:i:s');
    }

    /** Untuk input HTML type=date (YYYY-MM-DD) pada zona waktu lokal. */
    public static function localIsoDate(?string $db): string
    {
        return $db === null ? '' : self::fromDb($db)->setTimezone(self::local())->format('Y-m-d');
    }

    /**
     * Parse tanggal lokal secara ketat. Menerima "YYYY-MM-DD" (input HTML) atau "DD-MM-YYYY".
     * Tanggal tidak valid (mis. 30-02-2026) ditolak.
     */
    public static function parseLocalDate(?string $value): ?DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        foreach (['!Y-m-d' => '/^\d{4}-\d{2}-\d{2}$/', '!d-m-Y' => '/^\d{2}-\d{2}-\d{4}$/'] as $format => $pattern) {
            if (preg_match($pattern, $value)) {
                $dt = DateTimeImmutable::createFromFormat($format, $value, self::local());
                $errors = DateTimeImmutable::getLastErrors();
                if ($dt === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                    return null;
                }
                if ((int) $dt->format('Y') < 2000 || (int) $dt->format('Y') > 2100) {
                    return null;
                }
                return $dt;
            }
        }
        return null;
    }

    /** Parse tanggal + waktu lokal (HH:mm:ss atau HH:mm) -> UTC. */
    public static function parseLocalDateTime(?string $date, ?string $time): ?DateTimeImmutable
    {
        $d = self::parseLocalDate($date);
        $time = trim((string) $time);
        if ($d === null || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $time, $m)) {
            return null;
        }
        return $d->setTime((int) $m[1], (int) $m[2], (int) ($m[3] ?? 0))->setTimezone(self::utc());
    }

    /** Batas UTC [awal, akhir) untuk rentang tanggal lokal (inklusif). */
    public static function localDateRangeToUtc(?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        return [
            $from ? self::toDb($from->setTime(0, 0, 0)) : null,
            $to ? self::toDb($to->setTime(0, 0, 0)->modify('+1 day')) : null,
        ];
    }

    /** Durasi ramah baca: 45 -> "00:45", 80 -> "01:20", 3725 -> "1:02:05". */
    public static function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%02d:%02d', $m, $s);
    }

    /** Usia kehamilan dari HPL (280 hari). Null bila HPL kosong/di luar rentang wajar. */
    public static function gestationalAge(?string $dueDate, ?DateTimeImmutable $today = null): ?array
    {
        if ($dueDate === null || $dueDate === '') {
            return null;
        }
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate, self::local());
        if ($due === false) {
            return null;
        }
        $today = ($today ?? new DateTimeImmutable('now', self::local()))->setTimezone(self::local())->setTime(0, 0);
        $daysToDue = (int) $today->diff($due)->format('%r%a');
        $gaDays = 280 - $daysToDue;
        if ($gaDays < 0 || $gaDays > 315) {
            return null;
        }
        return ['weeks' => intdiv($gaDays, 7), 'days' => $gaDays % 7, 'days_to_due' => $daysToDue];
    }
}
