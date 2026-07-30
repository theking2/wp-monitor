<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class Snapshot
{
    /** @return array<int, array<string, mixed>> */
    public static function forSite(int $siteId, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, content_hash, raw_length, similarity, is_signature, triggered_by, created_at
             FROM snapshots WHERE site_id = :site_id ORDER BY id DESC LIMIT :limit'
        );
        $stmt->bindValue(':site_id', $siteId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function latestSignature(int $siteId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM snapshots WHERE site_id = :site_id AND is_signature = 1 ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['site_id' => $siteId]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<string, mixed> */
    public static function create(
        int $siteId,
        string $hash,
        string $normalizedContent,
        ?float $similarity,
        bool $isSignature,
        string $triggeredBy
    ): array {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO snapshots (site_id, content_hash, normalized_content, raw_length, similarity, is_signature, triggered_by)
             VALUES (:site_id, :hash, :normalized, :raw_length, :similarity, :is_signature, :triggered_by)'
        );
        $stmt->execute([
            'site_id' => $siteId,
            'hash' => $hash,
            'normalized' => $normalizedContent,
            'raw_length' => strlen($normalizedContent),
            'similarity' => $similarity,
            'is_signature' => $isSignature ? 1 : 0,
            'triggered_by' => $triggeredBy,
        ]);

        return self::findRaw((int) $pdo->lastInsertId());
    }

    public static function promoteToSignature(int $snapshotId): void
    {
        $row = self::findRaw($snapshotId);
        if ($row === null) {
            return;
        }

        $pdo = Database::connection();
        $pdo->prepare('UPDATE snapshots SET is_signature = 0 WHERE site_id = :site_id')
            ->execute(['site_id' => $row['site_id']]);
        $pdo->prepare('UPDATE snapshots SET is_signature = 1 WHERE id = :id')
            ->execute(['id' => $snapshotId]);
    }

    /** @return array<string, mixed>|null */
    private static function findRaw(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM snapshots WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }
}
