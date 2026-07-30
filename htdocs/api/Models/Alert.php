<?php

namespace App\Models;

use App\Core\Database;

final class Alert
{
    /** @return array<int, array<string, mixed>> */
    public static function forSite(int $siteId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM alerts WHERE site_id = :site_id ORDER BY id DESC');
        $stmt->execute(['site_id' => $siteId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed> */
    public static function create(int $siteId, int $snapshotId, float $similarity, string $diffSummary): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO alerts (site_id, snapshot_id, similarity, diff_summary, status)
             VALUES (:site_id, :snapshot_id, :similarity, :diff_summary, :status)'
        );
        $stmt->execute([
            'site_id' => $siteId,
            'snapshot_id' => $snapshotId,
            'similarity' => $similarity,
            'diff_summary' => $diffSummary,
            'status' => 'open',
        ]);

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM alerts WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }
}
