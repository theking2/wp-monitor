<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class SiteOutage
{
    /** @return array<string, mixed>|null */
    public static function findOpen(int $siteId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM site_outages WHERE site_id = :site_id AND resolved_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['site_id' => $siteId]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed> */
    public static function open(int $siteId, string $errorMessage): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO site_outages (site_id, error_message) VALUES (:site_id, :error_message)');
        $stmt->execute(['site_id' => $siteId, 'error_message' => $errorMessage]);

        return self::find((int) $pdo->lastInsertId());
    }

    public static function resolve(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE site_outages SET resolved_at = :now WHERE id = :id');
        $stmt->execute(['now' => \date('c'), 'id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function forSite(int $siteId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM site_outages WHERE site_id = :site_id ORDER BY id DESC LIMIT :limit'
        );
        $stmt->bindValue(':site_id', $siteId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed> */
    private static function find(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM site_outages WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }
}
