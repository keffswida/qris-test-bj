<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;
use PDO;

/**
 * Koneksi MySQL via PDO. Seluruh query di aplikasi wajib menggunakan
 * prepared statements (lihat ApiTestLogRepository).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $host = (string) Config::get('DB_HOST', '127.0.0.1');
            $port = (string) Config::get('DB_PORT', '3306');
            $name = (string) Config::get('DB_DATABASE', 'test-qris-bj');
            $user = (string) Config::get('DB_USERNAME', 'root');
            $pass = (string) Config::get('DB_PASSWORD', '');

            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        }

        return self::$pdo;
    }

    /**
     * Cek ketersediaan database tanpa melempar exception.
     */
    public static function isAvailable(): bool
    {
        try {
            self::pdo();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reset koneksi (digunakan saat koneksi pertama gagal, agar bisa dicoba lagi).
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
