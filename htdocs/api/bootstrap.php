<?php

require __DIR__ . '/../vendor/autoload.php';

// Fallback .env loader so `php -S`/CLI usage outside docker compose still picks up
// config; inside the container, compose's `env_file` already populates these.
$envFile = __DIR__ . '/../../.wp-mon.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

date_default_timezone_set('UTC');
