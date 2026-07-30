<?php

namespace App\Models;

use App\Core\Database;

final class PluginUpdate
{
    /** @return array<int, array<string, mixed>> */
    public static function recent(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM plugin_updates ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed> */
    public static function create(string $pluginName, ?string $version, ?string $siteUrl, string $rawExcerpt): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO plugin_updates (plugin_name, version, site_url, raw_excerpt)
             VALUES (:plugin_name, :version, :site_url, :raw_excerpt)'
        );
        $stmt->execute([
            'plugin_name' => $pluginName,
            'version' => $version,
            'site_url' => $siteUrl,
            'raw_excerpt' => $rawExcerpt,
        ]);

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM plugin_updates WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }
}
