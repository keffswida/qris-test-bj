<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Sanitasi payload sebelum disimpan ke riwayat pengujian.
 *
 * Aturan (overview.md §4.5):
 * - Client secret, access token, password, dan credential lain wajib disamarkan.
 * - Access token dan signature tidak boleh disimpan plaintext di log.
 */
final class ResponseHelper
{
    /** Kunci yang dianggap sensitif. */
    private const SENSITIVE_KEY_PATTERNS = [
        '/secret/i',
        '/token/i',
        '/password/i',
        '/passwd/i',
        '/signature/i',
        '/private.?key/i',
        '/client.?key/i',
        '/authorization/i',
        '/credential/i',
    ];

    /** Nilai yang menyerupai JWT (eyJ...) dianggap access token. */
    private const JWT_VALUE_PATTERN = '/^eyJ[A-Za-z0-9\-_]+\./';

    public static function sanitizeAssoc(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    foreach (self::SENSITIVE_KEY_PATTERNS as $pattern) {
                        if (preg_match($pattern, $key) === 1) {
                            $item = '[REDACTED]';
                            break;
                        }
                    }
                }
                $out[$key] = self::sanitizeAssoc($item);
            }
            return $out;
        }

        if (is_string($value) && preg_match(self::JWT_VALUE_PATTERN, $value) === 1) {
            return '[REDACTED]';
        }

        return $value;
    }

    /**
     * Encode nilai menjadi JSON yang aman disimpan di kolom JSON.
     */
    public static function toJson(mixed $value): ?string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded === false ? null : $encoded;
    }

    /**
     * Render nilai sebagai JSON pretty untuk ditampilkan (payload sudah
     * disanitasi terlebih dahulu oleh caller).
     */
    public static function prettyJson(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if ($decoded !== null) {
                $value = $decoded;
            }
        }
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded === false ? (string) $value : $encoded;
    }
}
