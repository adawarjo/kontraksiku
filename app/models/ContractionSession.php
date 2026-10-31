<?php

declare(strict_types=1);

namespace App\Models;

final class ContractionSession extends Model
{
    public function findActive(int $userId, bool $forUpdate = false): ?array
    {
        return $this->fetch(
            "SELECT * FROM contraction_sessions WHERE user_id = ? AND status = 'active' LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''),
            [$userId]
        );
    }

    public function findLatest(int $userId): ?array
    {
        return $this->fetch('SELECT * FROM contraction_sessions WHERE user_id = ? ORDER BY started_at DESC, id DESC LIMIT 1', [$userId]);
    }

    public function findForUser(int $id, int $userId): ?array
    {
        return $this->fetch('SELECT * FROM contraction_sessions WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public function create(int $userId, string $startedAtUtc): int
    {
        $this->execute(
            "INSERT INTO contraction_sessions (user_id, started_at, status, active_user_id, created_at, updated_at)
             VALUES (?, ?, 'active', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$userId, $startedAtUtc, $userId]
        );
        return $this->lastId();
    }

    public function updateStart(int $id, string $startedAtUtc): void
    {
        $this->execute('UPDATE contraction_sessions SET started_at = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$startedAtUtc, $id]);
    }

    public function complete(int $id, string $endedAtUtc): void
    {
        $this->execute(
            "UPDATE contraction_sessions SET status = 'completed', ended_at = ?, active_user_id = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ?",
            [$endedAtUtc, $id]
        );
    }

    /** Sesi tertentu milik pengguna beserta jumlah kontraksinya, diindeks per id. */
    public function findManyForUser(array $ids, int $userId): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->fetchAll(
            "SELECT s.*, COUNT(c.id) AS contraction_count
               FROM contraction_sessions s
          LEFT JOIN contractions c ON c.session_id = s.id
              WHERE s.user_id = ? AND s.id IN ($placeholders)
           GROUP BY s.id",
            [$userId, ...$ids]
        );
        return array_column($rows, null, 'id');
    }

    /** Daftar sesi pengguna (terbaru dulu) dengan jumlah kontraksi. */
    public function listForUser(int $userId, int $limit = 20): array
    {
        return $this->fetchAll(
            'SELECT s.*, COUNT(c.id) AS contraction_count
               FROM contraction_sessions s
          LEFT JOIN contractions c ON c.session_id = s.id
              WHERE s.user_id = ?
           GROUP BY s.id
           ORDER BY s.started_at DESC, s.id DESC
              LIMIT ' . max(1, min(100, $limit)),
            [$userId]
        );
    }
}
