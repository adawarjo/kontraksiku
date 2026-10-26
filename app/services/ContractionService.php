<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Models\Contraction;
use App\Models\ContractionSession;
use App\Models\User;
use DateTimeImmutable;
use PDOException;

/**
 * Logika bisnis kontraksi.
 *
 * Rumus:
 *  - durasi   = ended_at - started_at
 *  - interval = started_at kontraksi ini - started_at kontraksi sebelumnya dalam sesi yang sama
 *               (BUKAN dari ended_at sebelumnya). Kontraksi pertama dalam sesi: NULL.
 *
 * Setiap operasi yang mengubah data berjalan dalam transaksi dan mengunci baris
 * pengguna (SELECT ... FOR UPDATE) sehingga klik ganda/permintaan paralel diproses berurutan.
 */
final class ContractionService
{
    public const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private Contraction $contractions;
    private ContractionSession $sessions;
    private User $users;

    public function __construct()
    {
        $this->contractions = new Contraction();
        $this->sessions = new ContractionSession();
        $this->users = new User();
    }

    /**
     * Simpan/perbarui kontraksi secara idempoten berdasarkan UUID dari perangkat.
     * - endedMs null  -> mulai kontraksi (atau kembalikan yang sudah ada).
     * - endedMs terisi -> selesaikan kontraksi yang ada, atau sisipkan kontraksi lengkap (sinkronisasi offline).
     *
     * @return array{contraction: array, created: bool, finished: bool}
     */
    public function sync(int $userId, mixed $uuid, mixed $startedMs, mixed $endedMs = null, mixed $notes = null): array
    {
        $uuid = strtolower(trim((string) $uuid));
        if (!preg_match(self::UUID_PATTERN, $uuid)) {
            throw new HttpException(422, 'ID kontraksi tidak valid.', ['uuid' => 'Format UUID tidak valid.']);
        }
        $start = TimeService::fromEpochMs($startedMs);
        if ($start === null) {
            throw new HttpException(422, 'Waktu mulai tidak valid.', ['started_at' => 'Waktu mulai tidak valid.']);
        }
        $end = null;
        if ($endedMs !== null && $endedMs !== '') {
            $end = TimeService::fromEpochMs($endedMs);
            if ($end === null) {
                throw new HttpException(422, 'Waktu selesai tidak valid.', ['ended_at' => 'Waktu selesai tidak valid.']);
            }
        }
        $notes = $this->cleanNotes($notes);

        try {
            return Database::transaction(function () use ($userId, $uuid, $start, $end, $notes) {
                $this->users->lockForUpdate($userId);

                $existing = $this->contractions->findByUuid($uuid);
                if ($existing !== null) {
                    return $this->syncExisting($userId, $existing, $end, $notes);
                }
                return $this->insertNew($userId, $uuid, $start, $end, $notes);
            });
        } catch (PDOException $e) {
            // Pelanggaran UNIQUE (uuid ganda / dua kontraksi aktif) akibat kondisi balapan
            if ($e->getCode() === '23000') {
                $existing = $this->contractions->findByUuid($uuid);
                if ($existing !== null && $existing['user_id'] === $userId) {
                    return ['contraction' => $existing, 'created' => false, 'finished' => $existing['ended_at'] !== null];
                }
                throw new HttpException(409, 'Masih ada kontraksi yang sedang berlangsung.');
            }
            throw $e;
        }
    }

    private function syncExisting(int $userId, array $existing, ?DateTimeImmutable $end, ?string $notes): array
    {
        if ($existing['user_id'] !== $userId) {
            // Jangan bocorkan keberadaan data milik pengguna lain
            throw new HttpException(409, 'ID kontraksi sudah digunakan. Silakan mulai ulang pencatatan.');
        }
        if ($end === null || $existing['ended_at'] !== null) {
            // Permintaan ulang (klik ganda / sinkronisasi berulang): tidak ada perubahan
            return ['contraction' => $existing, 'created' => false, 'finished' => false];
        }
        $duration = $this->validateDuration(TimeService::fromDb($existing['started_at']), $end);
        $this->assertNoOverlap($userId, TimeService::fromDb($existing['started_at']), $end, $existing['id']);
        $this->contractions->finish($existing['id'], TimeService::toDb($end), $duration, $notes);
        $this->recalculateSession($existing['session_id']);
        return [
            'contraction' => $this->contractions->findByUuid($existing['uuid']),
            'created'     => false,
            'finished'    => true,
        ];
    }

    private function insertNew(int $userId, string $uuid, DateTimeImmutable $start, ?DateTimeImmutable $end, ?string $notes): array
    {
        $now = TimeService::now();
        $skew = (int) config('app.max_clock_skew_seconds', 300);
        if ($start->getTimestamp() > $now->getTimestamp() + $skew) {
            throw new HttpException(422, 'Waktu mulai berada di masa depan. Periksa jam perangkat Anda.', ['started_at' => 'Waktu mulai di masa depan.']);
        }
        $maxAgeDays = (int) config('app.max_sync_age_days', 14);
        if ($start->getTimestamp() < $now->getTimestamp() - $maxAgeDays * 86400) {
            throw new HttpException(422, 'Waktu mulai terlalu lama dari sekarang.', ['started_at' => 'Waktu mulai terlalu lama.']);
        }

        $duration = null;
        if ($end !== null) {
            if ($end->getTimestamp() > $now->getTimestamp() + $skew) {
                throw new HttpException(422, 'Waktu selesai berada di masa depan.', ['ended_at' => 'Waktu selesai di masa depan.']);
            }
            $duration = $this->validateDuration($start, $end);
        } else {
            $active = $this->contractions->findActive($userId);
            if ($active !== null) {
                throw new HttpException(409, 'Masih ada kontraksi yang sedang berlangsung.', [], [
                    'active' => ContractionPresenter::toArray($active),
                ]);
            }
        }

        $this->assertNoOverlap($userId, $start, $end, null);

        $session = $this->sessions->findActive($userId, true);
        if ($session === null) {
            $sessionId = $this->sessions->create($userId, TimeService::toDb($start));
        } else {
            $sessionId = (int) $session['id'];
            if (TimeService::fromDb($session['started_at']) > $start) {
                $this->sessions->updateStart($sessionId, TimeService::toDb($start));
            }
        }

        $this->contractions->insert([
            'user_id'          => $userId,
            'session_id'       => $sessionId,
            'uuid'             => $uuid,
            'started_at'       => TimeService::toDb($start),
            'ended_at'         => $end ? TimeService::toDb($end) : null,
            'duration_seconds' => $duration,
            'notes'            => $notes,
        ]);
        $this->recalculateSession($sessionId);

        return [
            'contraction' => $this->contractions->findByUuid($uuid),
            'created'     => true,
            'finished'    => $end !== null,
        ];
    }

    /** Batalkan kontraksi yang sedang berlangsung (dihapus). Idempoten. */
    public function cancelActive(int $userId, mixed $uuid): bool
    {
        $uuid = strtolower(trim((string) $uuid));
        if (!preg_match(self::UUID_PATTERN, $uuid)) {
            throw new HttpException(422, 'ID kontraksi tidak valid.');
        }
        return Database::transaction(function () use ($userId, $uuid) {
            $this->users->lockForUpdate($userId);
            $row = $this->contractions->findByUuid($uuid);
            if ($row === null || $row['user_id'] !== $userId) {
                return false;
            }
            if ($row['ended_at'] !== null) {
                throw new HttpException(409, 'Kontraksi ini sudah selesai. Gunakan menu hapus pada riwayat.');
            }
            $this->contractions->delete($row['id'], $userId);
            $this->recalculateSession($row['session_id']);
            return true;
        });
    }

    /** Edit kontraksi yang sudah selesai (waktu mulai, selesai, catatan). */
    public function update(int $userId, int $id, ?DateTimeImmutable $start, ?DateTimeImmutable $end, mixed $notes): array
    {
        $errors = [];
        if ($start === null) {
            $errors['started_at'] = 'Tanggal/waktu mulai tidak valid.';
        }
        if ($end === null) {
            $errors['ended_at'] = 'Tanggal/waktu selesai tidak valid.';
        }
        if ($errors) {
            throw new HttpException(422, 'Periksa kembali isian Anda.', $errors);
        }
        $notes = $this->cleanNotes($notes);

        return Database::transaction(function () use ($userId, $id, $start, $end, $notes) {
            $this->users->lockForUpdate($userId);
            $row = $this->contractions->findForUser($id, $userId);
            if ($row === null) {
                throw new HttpException(404, 'Data kontraksi tidak ditemukan.');
            }
            if ($row['ended_at'] === null) {
                throw new HttpException(409, 'Kontraksi yang sedang berlangsung tidak dapat diedit. Akhiri terlebih dahulu.');
            }
            if ($end->getTimestamp() > TimeService::now()->getTimestamp() + (int) config('app.max_clock_skew_seconds', 300)) {
                throw new HttpException(422, 'Waktu selesai tidak boleh di masa depan.', ['ended_at' => 'Waktu selesai di masa depan.']);
            }
            $duration = $this->validateDuration($start, $end);
            $this->assertNoOverlap($userId, $start, $end, $id);
            $this->contractions->updateTimes($id, $userId, TimeService::toDb($start), TimeService::toDb($end), $duration, $notes);

            $session = $this->sessions->findForUser($row['session_id'], $userId);
            if ($session !== null && TimeService::fromDb($session['started_at']) > $start) {
                $this->sessions->updateStart($row['session_id'], TimeService::toDb($start));
            }
            $this->recalculateSession($row['session_id']);
            return $this->contractions->findForUser($id, $userId);
        });
    }

    public function delete(int $userId, int $id): void
    {
        Database::transaction(function () use ($userId, $id) {
            $this->users->lockForUpdate($userId);
            $row = $this->contractions->findForUser($id, $userId);
            if ($row === null) {
                throw new HttpException(404, 'Data kontraksi tidak ditemukan.');
            }
            $this->contractions->delete($id, $userId);
            $this->recalculateSession($row['session_id']);
        });
    }

    /** Akhiri sesi pencatatan aktif. Kontraksi yang masih berjalan harus diakhiri terlebih dahulu. */
    public function endSession(int $userId): ?array
    {
        return Database::transaction(function () use ($userId) {
            $this->users->lockForUpdate($userId);
            if ($this->contractions->findActive($userId) !== null) {
                throw new HttpException(409, 'Akhiri kontraksi yang sedang berlangsung sebelum menutup sesi.');
            }
            $session = $this->sessions->findActive($userId, true);
            if ($session === null) {
                return null;
            }
            $rows = $this->contractions->listBySession((int) $session['id']);
            $endedAt = TimeService::toDb(TimeService::now());
            if ($rows) {
                $last = end($rows);
                $endedAt = $last['ended_at'] ?? $endedAt;
            }
            $this->sessions->complete((int) $session['id'], $endedAt);
            return $this->sessions->findForUser((int) $session['id'], $userId);
        });
    }

    /** Hitung ulang interval seluruh kontraksi dalam sesi (start-ke-start, urut waktu mulai). */
    public function recalculateSession(int $sessionId): void
    {
        $previousStart = null;
        foreach ($this->contractions->listBySession($sessionId) as $row) {
            $interval = $previousStart === null ? null : TimeService::diffSeconds($previousStart, $row['started_at']);
            if ($interval !== null && $interval < 0) {
                $interval = null;
            }
            if ($interval !== $row['interval_seconds']) {
                $this->contractions->setInterval($row['id'], $interval);
            }
            $previousStart = $row['started_at'];
        }
    }

    private function validateDuration(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        $duration = $end->getTimestamp() - $start->getTimestamp();
        if ($duration < 1) {
            throw new HttpException(422, 'Waktu selesai harus setelah waktu mulai.', ['ended_at' => 'Waktu selesai harus setelah waktu mulai.']);
        }
        $max = (int) config('app.max_contraction_seconds', 10800);
        if ($duration > $max) {
            throw new HttpException(422, sprintf(
                'Durasi kontraksi lebih dari %d jam sehingga dianggap salah catat. Batalkan atau perbaiki waktunya.',
                intdiv($max, 3600)
            ), ['ended_at' => 'Durasi terlalu panjang.']);
        }
        return $duration;
    }

    /** Tolak kontraksi yang waktunya bertabrakan dengan kontraksi lain milik pengguna. */
    private function assertNoOverlap(int $userId, DateTimeImmutable $start, ?DateTimeImmutable $end, ?int $excludeId): void
    {
        $s = $start->getTimestamp();
        $e = $end?->getTimestamp() ?? PHP_INT_MAX; // kontraksi aktif dianggap terbuka ke depan
        $toDb = $end ? TimeService::toDb($end) : '2100-01-01 00:00:00';
        foreach ($this->contractions->listAround($userId, TimeService::toDb($start), $toDb, $excludeId) as $other) {
            $os = TimeService::fromDb($other['started_at'])->getTimestamp();
            $oe = $other['ended_at'] === null ? PHP_INT_MAX : TimeService::fromDb($other['ended_at'])->getTimestamp();
            if ($s < $oe && $os < $e) {
                throw new HttpException(409, sprintf(
                    'Waktu kontraksi bertabrakan dengan kontraksi lain (%s %s).',
                    TimeService::localDate($other['started_at']),
                    TimeService::localTime($other['started_at'])
                ));
            }
        }
    }

    private function cleanNotes(mixed $notes): ?string
    {
        if ($notes === null) {
            return null;
        }
        $notes = trim(strip_tags((string) $notes));
        if ($notes === '') {
            return null;
        }
        if (mb_strlen($notes) > 500) {
            throw new HttpException(422, 'Catatan maksimal 500 karakter.', ['notes' => 'Catatan maksimal 500 karakter.']);
        }
        return $notes;
    }
}
