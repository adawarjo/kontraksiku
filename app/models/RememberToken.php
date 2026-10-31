<?php

declare(strict_types=1);

namespace App\Models;

/** Token login "ingat saya" per perangkat. Hanya hash validator yang disimpan. */
final class RememberToken extends Model
{
    /** $expiresAt dalam epoch detik. */
    public function create(int $userId, string $selector, string $tokenHash, int $sessionVersion, int $expiresAt): void
    {
        $this->execute(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, session_version, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$userId, $selector, $tokenHash, $sessionVersion, gmdate('Y-m-d H:i:s', $expiresAt)]
        );
    }

    public function findBySelector(string $selector): ?array
    {
        $row = $this->fetch(
            'SELECT id, user_id, token_hash, session_version, expires_at <= UTC_TIMESTAMP() AS expired
             FROM remember_tokens WHERE selector = ?',
            [$selector]
        );
        if ($row === null) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['session_version'] = (int) $row['session_version'];
        $row['expired'] = (bool) (int) $row['expired'];
        return $row;
    }

    public function touch(int $id): void
    {
        $this->execute('UPDATE remember_tokens SET last_used_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    public function deleteBySelector(string $selector): void
    {
        $this->execute('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
    }

    /** Hapus token kedaluwarsa dan token yang sudah tidak berlaku (password berubah / akun nonaktif). */
    public function purgeInvalid(): void
    {
        $this->execute(
            'DELETE rt FROM remember_tokens rt JOIN users u ON u.id = rt.user_id
             WHERE rt.expires_at <= UTC_TIMESTAMP() OR rt.session_version <> u.session_version OR u.is_active = 0'
        );
    }
}
