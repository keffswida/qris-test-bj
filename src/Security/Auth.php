<?php

declare(strict_types=1);

namespace App\Security;

use App\Config\Config;

/**
 * Autentikasi sederhana dashboard (session + password dari .env) dan CSRF.
 *
 * Autentikasi aktif hanya bila APP_AUTH_PASSWORD diisi — wajib diisi saat
 * aplikasi dapat diakses dari jaringan publik (overview.md §9).
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user';
    private const CSRF_KEY = 'csrf_token';

    public static function enabled(): bool
    {
        $password = Config::get('APP_AUTH_PASSWORD');
        return $password !== null && $password !== '';
    }

    public static function check(): bool
    {
        return !self::enabled() || !empty($_SESSION[self::SESSION_KEY]);
    }

    public static function attempt(string $password): bool
    {
        $expected = (string) Config::get('APP_AUTH_PASSWORD', '');
        if (!self::enabled() || !hash_equals($expected, $password)) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = true;
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    /** Hidden input untuk formulir POST. */
    public static function csrfField(): string
    {
        $token = htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    public static function verifyCsrf(?string $token): bool
    {
        $expected = $_SESSION[self::CSRF_KEY] ?? '';
        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }
}
