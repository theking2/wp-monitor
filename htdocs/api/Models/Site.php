<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class Site
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM sites ORDER BY name COLLATE NOCASE')
            ->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM sites WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findByUrl(string $url): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM sites WHERE url = :url');
        $stmt->execute(['url' => $url]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed> */
    public static function create(string $name, string $url): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO sites (name, url, status) VALUES (:name, :url, :status)');
        $stmt->execute(['name' => $name, 'url' => $url, 'status' => 'unknown']);

        return self::find((int) $pdo->lastInsertId());
    }

    /** @return array<string, mixed> */
    public static function firstOrCreateByUrl(string $url, string $name): array
    {
        return self::findByUrl($url) ?? self::create($name, $url);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE sites SET status = :status, last_checked_at = :now, updated_at = :now WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'now' => date('c'), 'id' => $id]);
    }
}
