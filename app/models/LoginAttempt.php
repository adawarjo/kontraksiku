<?php

declare(strict_types=1);

namespace App\Models;

final class LoginAttempt extends Model
{
    public function record(string $email, string $ip): void
    {
        $this->execute('INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, UTC_TIMESTAMP())', [$email, $ip]);
    }

    /** Jumlah percobaan gagal untuk email ATAU IP dalam jendela waktu. */
    public function countRecent(string $email, string $ip, int $minutes): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $minutes * 60);
        $byEmail = $this->fetch('SELECT COUNT(*) AS n FROM login_attempts WHERE email = ? AND attempted_at >= ?', [$email, $since]);
        $byIp    = $this->fetch('SELECT COUNT(*) AS n FROM login_attempts WHERE ip_address = ? AND attempted_at >= ?', [$ip, $since]);
        return ['email' => (int) ($byEmail['n'] ?? 0), 'ip' => (int) ($byIp['n'] ?? 0)];
    }

    public function clear(string $email): void
    {
        $this->execute('DELETE FROM login_attempts WHERE email = ?', [$email]);
    }

    public function purgeOlderThan(int $minutes): void
    {
        $this->execute('DELETE FROM login_attempts WHERE attempted_at < ?', [gmdate('Y-m-d H:i:s', time() - $minutes * 60)]);
    }
}
