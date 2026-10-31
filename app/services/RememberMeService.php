<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RememberToken;
use App\Models\User;

/**
 * Login "ingat saya": perangkat dipercaya selama N hari (default 30) sejak login.
 *
 * Nilai cookie = selector:validator. Selector untuk mencari baris, validator
 * dibandingkan dengan hash SHA-256 di database (kebocoran database tidak
 * memberi cookie yang valid). Token tidak berlaku bila kedaluwarsa, akun
 * dinonaktifkan, atau session_version berubah (password diganti/direset).
 * Masa berlaku tidak diperpanjang saat dipakai: setelah N hari wajib login ulang.
 */
final class RememberMeService
{
    private const FORMAT = '/^([a-f0-9]{24}):([a-f0-9]{64})$/';

    private RememberToken $tokens;

    public function __construct()
    {
        $this->tokens = new RememberToken();
    }

    public static function days(): int
    {
        return max(1, (int) config('app.remember_days', 30));
    }

    /**
     * Buat token baru untuk pengguna.
     * @return array{value: string, expires: int} nilai cookie & waktu kedaluwarsa (epoch detik)
     */
    public function issue(array $user): array
    {
        $this->tokens->purgeInvalid();
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + self::days() * 86400;
        $this->tokens->create((int) $user['id'], $selector, hash('sha256', $validator), (int) $user['session_version'], $expires);
        return ['value' => $selector . ':' . $validator, 'expires' => $expires];
    }

    /** @return array|null pengguna (kolom publik) bila token sah, selain itu null (token tidak sah dihapus). */
    public function resolve(string $value): ?array
    {
        if (!preg_match(self::FORMAT, $value, $m)) {
            return null;
        }
        [, $selector, $validator] = $m;
        $token = $this->tokens->findBySelector($selector);
        if ($token === null) {
            return null;
        }
        $user = (new User())->find($token['user_id']);
        $valid = hash_equals($token['token_hash'], hash('sha256', $validator))
            && !$token['expired']
            && $user !== null
            && $user['is_active']
            && $user['session_version'] === $token['session_version'];
        if (!$valid) {
            $this->tokens->deleteBySelector($selector);
            return null;
        }
        $this->tokens->touch($token['id']);
        return $user;
    }

    public function revoke(string $value): void
    {
        if (preg_match(self::FORMAT, $value, $m)) {
            $this->tokens->deleteBySelector($m[1]);
        }
    }
}
