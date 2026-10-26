<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Basis model: akses PDO dengan prepared statements. */
abstract class Model
{
    protected function db(): PDO
    {
        return Database::connection();
    }

    protected function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, $type);
        }
        $stmt->execute();
        return $stmt->rowCount();
    }

    protected function lastId(): int
    {
        return (int) $this->db()->lastInsertId();
    }
}
