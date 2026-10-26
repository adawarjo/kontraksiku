<?php

declare(strict_types=1);

namespace App\Core;

/** Akses data request HTTP. */
final class Request
{
    private static ?array $jsonBody = null;

    /** Base path aplikasi, mis. "/kontraksiku/public" atau "" bila di root domain. */
    public static function basePath(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $dir    = $dir === '.' ? '' : $dir;

        // Diakses lewat .htaccess root (mis. /kontraksiku/) yang meneruskan ke public/:
        // URL tidak memuat "/public", jadi base path harus tanpa "/public".
        if (str_ends_with($dir, '/public')) {
            $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if ($uri !== $dir && !str_starts_with($uri, $dir . '/')) {
                $dir = substr($dir, 0, -strlen('/public'));
            }
        }
        return $dir;
    }

    /** Path route tanpa base path, selalu diawali "/". */
    public static function path(): string
    {
        if (isset($_GET['route']) && is_string($_GET['route'])) {
            $path = $_GET['route'];
        } else {
            $uri  = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $base = self::basePath();
            if ($base !== '' && str_starts_with($uri, $base)) {
                $uri = substr($uri, strlen($base));
            }
            if (str_starts_with($uri, '/index.php')) {
                $uri = substr($uri, strlen('/index.php'));
            }
            $path = $uri;
        }
        $path = '/' . trim(rawurldecode($path), '/');
        return $path;
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isApi(): bool
    {
        return str_starts_with(self::path(), '/api/');
    }

    /** Ambil input dari body JSON atau form POST. */
    public static function input(string $key, mixed $default = null): mixed
    {
        $json = self::json();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }
        return $_POST[$key] ?? $default;
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public static function json(): array
    {
        if (self::$jsonBody === null) {
            self::$jsonBody = [];
            $type = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($type, 'application/json')) {
                $raw = file_get_contents('php://input') ?: '';
                if (strlen($raw) > 65536) {
                    throw new HttpException(413, 'Data yang dikirim terlalu besar.');
                }
                $decoded = json_decode($raw, true);
                if ($raw !== '' && !is_array($decoded)) {
                    throw new HttpException(400, 'Format data tidak valid.');
                }
                self::$jsonBody = $decoded ?? [];
            }
        }
        return self::$jsonBody;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
