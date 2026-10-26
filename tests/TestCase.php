<?php

declare(strict_types=1);

/*
 * Kerangka uji ringan tanpa dependensi (tidak perlu Composer/PHPUnit).
 * Menggunakan database terpisah dari .env.testing yang dibuat ulang setiap run.
 */

$envFile = dirname(__DIR__) . '/.env.testing';
if (!is_file($envFile)) {
    fwrite(STDERR, "File .env.testing tidak ditemukan. Salin .env.testing.example menjadi .env.testing.\n");
    exit(2);
}
putenv('KK_ENV_FILE=' . $envFile);
require dirname(__DIR__) . '/app/bootstrap.php';

final class T
{
    public static int $passed = 0;
    public static array $failures = [];
    private static string $current = '';

    public static function test(string $name, callable $fn): void
    {
        self::$current = $name;
        try {
            $fn();
            self::$passed++;
            echo "  \033[32m✔\033[0m $name\n";
        } catch (Throwable $e) {
            self::$failures[] = [$name, $e];
            echo "  \033[31m✘ $name\033[0m\n     → " . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
        }
    }

    public static function eq(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($msg ? "$msg: " : '') . 'diharapkan ' . var_export($expected, true) . ', didapat ' . var_export($actual, true));
        }
    }

    public static function ok(bool $cond, string $msg): void
    {
        if (!$cond) {
            throw new RuntimeException($msg);
        }
    }

    /** Pastikan callback melempar HttpException dengan status tertentu. */
    public static function throwsHttp(int $status, callable $fn, string $msg = ''): App\Core\HttpException
    {
        try {
            $fn();
        } catch (App\Core\HttpException $e) {
            self::eq($status, $e->status, $msg ?: 'status HTTP');
            return $e;
        }
        throw new RuntimeException(($msg ? "$msg: " : '') . "diharapkan HttpException $status, tidak ada exception");
    }

    public static function summary(): int
    {
        $total = self::$passed + count(self::$failures);
        echo "\n" . (self::$failures ? "\033[31m" : "\033[32m") . self::$passed . "/$total lulus\033[0m\n";
        return self::$failures ? 1 : 0;
    }
}

/** Buat ulang database uji dari database/schema.sql. */
function reset_test_database(): void
{
    $c = config('database');
    if (!str_contains($c['database'], 'test')) {
        fwrite(STDERR, "Nama database uji harus mengandung kata 'test' (keamanan).\n");
        exit(2);
    }
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], $c['port']), $c['username'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('DROP DATABASE IF EXISTS `' . $c['database'] . '`');
    $sql = file_get_contents(BASE_PATH . '/database/schema.sql');
    $sql = str_replace('`kontraksiku`', '`' . $c['database'] . '`', $sql);
    // Jalankan per pernyataan (abaikan komentar)
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $pdo->exec($stmt);
    }
    App\Core\Database::setConnection(null);
}

function make_user(string $email, string $password = 'rahasia123'): int
{
    return (new App\Models\User())->create('Pengguna ' . $email, $email, password_hash($password, PASSWORD_DEFAULT));
}

function uuid4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    $h = bin2hex($b);
    return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20));
}

/** Epoch ms untuk N detik yang lalu. */
function ago(int $seconds): int
{
    return (time() - $seconds) * 1000;
}

function db_count(string $sql, array $params = []): int
{
    $stmt = App\Core\Database::connection()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}
