<?php

declare(strict_types=1);

namespace App\Models;

final class Contraction extends Model
{
    private const COLUMNS = 'id, user_id, session_id, uuid, started_at, ended_at, duration_seconds, interval_seconds, notes, created_at, updated_at';

    public function findByUuid(string $uuid): ?array
    {
        return $this->cast($this->fetch('SELECT ' . self::COLUMNS . ' FROM contractions WHERE uuid = ?', [$uuid]));
    }

    public function findForUser(int $id, int $userId): ?array
    {
        return $this->cast($this->fetch('SELECT ' . self::COLUMNS . ' FROM contractions WHERE id = ? AND user_id = ?', [$id, $userId]));
    }

    public function findActive(int $userId): ?array
    {
        return $this->cast($this->fetch('SELECT ' . self::COLUMNS . ' FROM contractions WHERE user_id = ? AND ended_at IS NULL LIMIT 1', [$userId]));
    }

    public function insert(array $d): int
    {
        $this->execute(
            'INSERT INTO contractions
                (user_id, session_id, uuid, started_at, ended_at, duration_seconds, interval_seconds, notes, active_user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [
                $d['user_id'], $d['session_id'], $d['uuid'], $d['started_at'], $d['ended_at'],
                $d['duration_seconds'], $d['notes'], $d['ended_at'] === null ? $d['user_id'] : null,
            ]
        );
        return $this->lastId();
    }

    public function finish(int $id, string $endedAtUtc, int $durationSeconds, ?string $notes): void
    {
        $this->execute(
            'UPDATE contractions
                SET ended_at = ?, duration_seconds = ?, notes = COALESCE(?, notes), active_user_id = NULL, updated_at = UTC_TIMESTAMP()
              WHERE id = ?',
            [$endedAtUtc, $durationSeconds, $notes, $id]
        );
    }

    public function updateTimes(int $id, int $userId, string $startUtc, string $endUtc, int $duration, ?string $notes): void
    {
        $this->execute(
            'UPDATE contractions
                SET started_at = ?, ended_at = ?, duration_seconds = ?, notes = ?, updated_at = UTC_TIMESTAMP()
              WHERE id = ? AND user_id = ?',
            [$startUtc, $endUtc, $duration, $notes, $id, $userId]
        );
    }

    public function delete(int $id, int $userId): int
    {
        return $this->execute('DELETE FROM contractions WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /** Kontraksi milik pengguna yang beririsan dengan rentang waktu tertentu. */
    public function listAround(int $userId, string $fromUtc, string $toUtc, ?int $excludeId = null): array
    {
        return array_map([$this, 'cast'], $this->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM contractions
              WHERE user_id = ? AND id <> ? AND started_at <= ? AND (ended_at IS NULL OR ended_at >= ?)',
            [$userId, $excludeId ?? 0, $toUtc, $fromUtc]
        ));
    }

    public function listBySession(int $sessionId, ?int $userId = null): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM contractions WHERE session_id = ?';
        $params = [$sessionId];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        return array_map([$this, 'cast'], $this->fetchAll($sql . ' ORDER BY started_at ASC, id ASC', $params));
    }

    public function setInterval(int $id, ?int $interval): void
    {
        $this->execute('UPDATE contractions SET interval_seconds = ? WHERE id = ?', [$interval, $id]);
    }

    public function listStartedSince(int $userId, string $sinceUtc): array
    {
        return array_map([$this, 'cast'], $this->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM contractions WHERE user_id = ? AND started_at >= ? ORDER BY started_at ASC, id ASC',
            [$userId, $sinceUtc]
        ));
    }

    public function recent(int $userId, int $limit = 5): array
    {
        return array_map([$this, 'cast'], $this->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM contractions WHERE user_id = ? ORDER BY started_at DESC, id DESC LIMIT ' . max(1, min(50, $limit)),
            [$userId]
        ));
    }

    /**
     * Riwayat dengan filter rentang (UTC, batas akhir eksklusif), urutan, dan pagination.
     * @return array{rows: array, total: int}
     */
    public function history(int $userId, ?string $fromUtc, ?string $toUtc, string $sort, string $dir, int $limit, int $offset): array
    {
        [$where, $params] = $this->historyWhere($userId, $fromUtc, $toUtc);
        $sortColumns = ['started_at' => 'started_at', 'duration' => 'duration_seconds', 'interval' => 'interval_seconds'];
        $column = $sortColumns[$sort] ?? 'started_at';
        $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $total = (int) ($this->fetch("SELECT COUNT(*) AS n FROM contractions WHERE $where", $params)['n'] ?? 0);
        $rows = $this->fetchAll(
            'SELECT ' . self::COLUMNS . " FROM contractions WHERE $where ORDER BY $column $direction, id $direction LIMIT "
            . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
        return ['rows' => array_map([$this, 'cast'], $rows), 'total' => $total];
    }

    /** Statistik agregat untuk rentang riwayat. */
    public function historyStats(int $userId, ?string $fromUtc, ?string $toUtc): array
    {
        [$where, $params] = $this->historyWhere($userId, $fromUtc, $toUtc);
        $row = $this->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(ended_at IS NOT NULL) AS completed,
                    AVG(duration_seconds) AS avg_duration,
                    MIN(duration_seconds) AS min_duration,
                    MAX(duration_seconds) AS max_duration,
                    AVG(interval_seconds) AS avg_interval,
                    MIN(interval_seconds) AS min_interval,
                    MAX(interval_seconds) AS max_interval
               FROM contractions WHERE $where",
            $params
        ) ?? [];
        return array_map(static fn ($v) => $v === null ? null : (int) round((float) $v), $row);
    }

    private function historyWhere(int $userId, ?string $fromUtc, ?string $toUtc): array
    {
        $where = 'user_id = ?';
        $params = [$userId];
        if ($fromUtc !== null) {
            $where .= ' AND started_at >= ?';
            $params[] = $fromUtc;
        }
        if ($toUtc !== null) {
            $where .= ' AND started_at < ?';
            $params[] = $toUtc;
        }
        return [$where, $params];
    }

    public function cast(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        foreach (['id', 'user_id', 'session_id'] as $k) {
            $row[$k] = (int) $row[$k];
        }
        foreach (['duration_seconds', 'interval_seconds'] as $k) {
            $row[$k] = $row[$k] === null ? null : (int) $row[$k];
        }
        return $row;
    }
}
