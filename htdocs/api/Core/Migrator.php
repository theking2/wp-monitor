<?php

namespace App\Core;

use PDO;

final class Migrator
{
    public static function run(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            name TEXT PRIMARY KEY,
            applied_at TEXT NOT NULL
        )');

        $applied = $pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

        $files = \glob(__DIR__ . '/../../database/migrations/*.sql') ?: [];
        \sort($files);

        foreach ($files as $file) {
            $name = \basename($file);
            if (\in_array($name, $applied, true)) {
                continue;
            }

            $pdo->exec((string) \file_get_contents($file));

            $stmt = $pdo->prepare('INSERT INTO migrations (name, applied_at) VALUES (:name, :applied_at)');
            $stmt->execute(['name' => $name, 'applied_at' => \date('c')]);
        }
    }
}
