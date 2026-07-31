<?php

namespace App\Core;

use Kingsoft\MonologHandler\CronRotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

final class Logger
{
    private static ?MonologLogger $instance = null;

    /**
     * Never throws: if the configured rotating file handler can't be set up (bad LOG_PATH,
     * permissions, ...), logging must not be the reason the caller's own error handling never
     * runs — fall back to stderr (visible in cron/task output) instead.
     */
    public static function get(): MonologLogger
    {
        if (self::$instance === null) {
            $level = Level::fromName(getenv('LOG_LEVEL') ?: 'info');
            $logger = new MonologLogger('wp-monitor');

            try {
                $path = getenv('LOG_PATH') ?: __DIR__ . '/../../../logs/wp-monitor.log';

                $logger->pushHandler(new CronRotatingFileHandler(
                    $path,
                    $level,
                    [
                        'cronExpression' => getenv('LOG_ROTATE_CRON') ?: '0 0 * * *',
                        'maxFiles' => (int) (getenv('LOG_ROTATE_MAX_FILES') ?: 14),
                        'minSize' => (int) (getenv('LOG_ROTATE_MIN_SIZE') ?: 0),
                        'compress' => filter_var(getenv('LOG_ROTATE_COMPRESS') ?: false, FILTER_VALIDATE_BOOLEAN),
                    ]
                ));
            } catch (\Throwable $e) {
                $logger->pushHandler(new StreamHandler('php://stderr', $level));
                $logger->warning('Falling back to stderr logging — could not set up the configured log file', [
                    'error' => $e->getMessage(),
                ]);
            }

            self::$instance = $logger;
        }

        return self::$instance;
    }
}
