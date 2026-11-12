<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Loader konfigurasi dari file .env (format KEY=VALUE) dengan fallback ke
 * environment variables (untuk Docker). Tidak ada nilai credential yang
 * di-hardcode di sini.
 */
final class Config
{
    /** @var array<string, string> */
    private static array $items = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    $pos = strpos($line, '=');
                    if ($pos === false) {
                        continue;
                    }
                    $key = trim(substr($line, 0, $pos));
                    $value = trim(substr($line, $pos + 1));
                    if ($key === '') {
                        continue;
                    }
                    // Strip optional quotes
                    $len = strlen($value);
                    if ($len >= 2
                        && (($value[0] === '"' && $value[$len - 1] === '"')
                            || ($value[0] === "'" && $value[$len - 1] === "'"))
                    ) {
                        $value = substr($value, 1, -1);
                    }
                    self::$items[$key] = $value;
                }
            }
        }

        // Environment variables (Docker) meng-override nilai .env.
        foreach (self::$items as $key => $_) {
            $env = getenv($key);
            if ($env !== false) {
                self::$items[$key] = $env;
            }
        }
    }

    /**
     * Mengembalikan nilai konfigurasi. Nilai kosong dianggap tidak diisi
     * sehingga mengembalikan $default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$items)) {
            $value = self::$items[$key];
            return $value === '' ? $default : $value;
        }
        $env = getenv($key);
        return ($env !== false && $env !== '') ? $env : $default;
    }

    /**
     * Wajib terisi: mengembalikan nilai atau melempar exception dengan
     * pesan yang aman ditampilkan.
     */
    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Konfigurasi '{$key}' belum diisi di .env.");
        }
        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return ($value !== null && is_numeric($value)) ? (int) $value : $default;
    }
}
