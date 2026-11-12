<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Config\Config;

/**
 * Logger file sederhana ke storage/logs/app-YYYY-MM-DD.log.
 * Caller bertanggung jawab memastikan pesan yang dikirim TIDAK mengandung
 * credential, access token, atau private key.
 */
final class Logger
{
    private const LEVELS = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];

    public static function log(string $level, string $message): void
    {
        $level = strtoupper($level);
        if (!in_array($level, self::LEVELS, true)) {
            $level = 'INFO';
        }
        if ($level === 'DEBUG' && !Config::bool('APP_DEBUG', false)) {
            return;
        }

        try {
            $dir = APP_ROOT . '/storage/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
            @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Kegagalan logging tidak boleh menutupi hasil pengujian.
        }
    }

    public static function debug(string $message): void
    {
        self::log('DEBUG', $message);
    }

    public static function info(string $message): void
    {
        self::log('INFO', $message);
    }

    public static function warning(string $message): void
    {
        self::log('WARNING', $message);
    }

    public static function error(string $message): void
    {
        self::log('ERROR', $message);
    }
}
