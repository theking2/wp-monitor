<?php

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $path = \getenv('DB_PATH') ?: __DIR__ . '/../../database/wp-monitor.sqlite';

            $dir = \dirname($path);
            if (!\is_dir($dir)) {
                \mkdir($dir, 0775, true);
            }

            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON');

            self::$instance = $pdo;
            Migrator::run($pdo);
        }

        return self::$instance;
    }
}
