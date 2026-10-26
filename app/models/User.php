<?php

declare(strict_types=1);

namespace App\Models;

final class User extends Model
{
    private const PUBLIC_COLUMNS = 'id, name, email, role, is_active, must_change_password, session_version, last_login_at, created_at, updated_at';

    public function find(int $id): ?array
    {
        return $this->cast($this->fetch('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE id = ?', [$id]));
    }

    /** Termasuk hash password; hanya untuk proses autentikasi. */
    public function findForAuth(string $email): ?array
    {
        return $this->cast($this->fetch('SELECT ' . self::PUBLIC_COLUMNS . ', password FROM users WHERE email = ?', [$email]));
    }

    public function findPasswordHash(int $id): ?string
    {
        $row = $this->fetch('SELECT password FROM users WHERE id = ?', [$id]);
        return $row['password'] ?? null;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        return $this->fetch('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $exceptId ?? 0]) !== null;
    }

    public function create(string $name, string $email, string $passwordHash, string $role = 'user', bool $mustChange = false): int
    {
        $this->execute(
            'INSERT INTO users (name, email, password, role, must_change_password, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$name, $email, $passwordHash, $role, $mustChange ? 1 : 0]
        );
        return $this->lastId();
    }

    /** Hanya memperbarui hash (rehash algoritma) tanpa memutus sesi. */
    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->execute('UPDATE users SET password = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$hash, $id]);
    }

    /**
     * Ganti password & naikkan session_version (semua sesi lama berakhir).
     * $mustChange = true saat admin mereset (pengguna wajib mengganti saat login).
     */
    public function setPassword(int $id, string $hash, bool $mustChange): void
    {
        $this->execute(
            'UPDATE users SET password = ?, must_change_password = ?, session_version = session_version + 1, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$hash, $mustChange ? 1 : 0, $id]
        );
    }

    public function updateName(int $id, string $name): void
    {
        $this->execute('UPDATE users SET name = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?', [$name, $id]);
    }

    public function updateAccount(int $id, string $name, string $email, string $role): void
    {
        $this->execute(
            'UPDATE users SET name = ?, email = ?, role = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$name, $email, $role, $id]
        );
    }

    /** Aktif/nonaktifkan akun. Menonaktifkan juga memutus semua sesi login. */
    public function setActive(int $id, bool $active): void
    {
        $this->execute(
            'UPDATE users SET is_active = ?, session_version = session_version + ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$active ? 1 : 0, $active ? 0 : 1, $id]
        );
    }

    public function touchLogin(int $id): void
    {
        $this->execute('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    public function countActiveAdmins(?int $exceptId = null): int
    {
        $row = $this->fetch("SELECT COUNT(*) AS n FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?", [$exceptId ?? 0]);
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Daftar pengguna untuk admin (tanpa data kesehatan).
     * @return array{rows: array, total: int}
     */
    public function search(string $q, string $role, string $status, int $limit, int $offset): array
    {
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (name LIKE ? OR email LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (in_array($role, ['user', 'admin'], true)) {
            $where .= ' AND role = ?';
            $params[] = $role;
        }
        if ($status === 'aktif' || $status === 'nonaktif') {
            $where .= ' AND is_active = ?';
            $params[] = $status === 'aktif' ? 1 : 0;
        }
        $total = (int) ($this->fetch("SELECT COUNT(*) AS n FROM users WHERE $where", $params)['n'] ?? 0);
        $rows = $this->fetchAll(
            'SELECT ' . self::PUBLIC_COLUMNS . " FROM users WHERE $where ORDER BY created_at DESC, id DESC LIMIT "
            . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        );
        return ['rows' => array_map([$this, 'cast'], $rows), 'total' => $total];
    }

    public function counts(): array
    {
        $row = $this->fetch(
            "SELECT COUNT(*) AS total, SUM(role = 'admin') AS admins, SUM(is_active = 0) AS inactive FROM users"
        ) ?? [];
        return array_map(static fn ($v) => (int) $v, $row);
    }

    /** Menghapus pengguna; profil, sesi, dan kontraksi ikut terhapus (ON DELETE CASCADE). */
    public function delete(int $id): void
    {
        $this->execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    /** Kunci baris pengguna untuk menserialkan operasi kontraksi (mencegah race/klik ganda). */
    public function lockForUpdate(int $id): void
    {
        $this->fetch('SELECT id FROM users WHERE id = ? FOR UPDATE', [$id]);
    }

    /** Kunci seluruh baris admin agar pemeriksaan "admin terakhir" tidak balapan. */
    public function lockAdmins(): void
    {
        $this->fetchAll("SELECT id FROM users WHERE role = 'admin' FOR UPDATE");
    }

    private function cast(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['is_active'] = (bool) (int) $row['is_active'];
        $row['must_change_password'] = (bool) (int) $row['must_change_password'];
        $row['session_version'] = (int) $row['session_version'];
        return $row;
    }
}
