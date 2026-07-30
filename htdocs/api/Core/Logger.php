<?php

namespace App\Core;

use Kingsoft\MonologHandler\CronRotatingFileHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

final class Logger
{
    private static ?MonologLogger $instance = null;

    public static function get(): MonologLogger
    {
        if (self::$instance === null) {
            $path = getenv('LOG_PATH') ?: __DIR__ . '/../../../logs/wp-monitor.log';

            $handler = new CronRotatingFileHandler(
                $path,
                Level::fromName(getenv('LOG_LEVEL') ?: 'info'),
                [
                    'cronExpression' => getenv('LOG_ROTATE_CRON') ?: '0 0 * * *',
                    'maxFiles' => (int) (getenv('LOG_ROTATE_MAX_FILES') ?: 14),
                    'minSize' => (int) (getenv('LOG_ROTATE_MIN_SIZE') ?: 0),
                    'compress' => filter_var(getenv('LOG_ROTATE_COMPRESS') ?: false, FILTER_VALIDATE_BOOLEAN),
                ]
            );

            self::$instance = new MonologLogger('wp-monitor');
            self::$instance->pushHandler($handler);
        }

        return self::$instance;
    }
}
