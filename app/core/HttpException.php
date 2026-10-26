<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Exception dengan kode status HTTP dan pesan yang aman ditampilkan ke pengguna. */
class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errors = [],
        public readonly array $extra = [],
    ) {
        parent::__construct($message, $status);
    }
}
