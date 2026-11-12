<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;

/**
 * Pembuatan digital signature sesuai "Document Specification JatimAPI - SNAP v1.9.4":
 *
 * 1) Signature Access Token (untuk request Access Token B2B):
 *      StringToSign = Client_ID + "|" + X-TIMESTAMP
 *      Symmetric : X-SIGNATURE = base64(HMAC-SHA512(ClientSecret, StringToSign))
 *      Asymmetric: X-SIGNATURE = base64(SHA256withRSA(PrivateKey, StringToSign))
 *
 * 2) Signature Service (untuk endpoint web service):
 *      Symmetric:
 *      StringToSign = HTTPMethod + ":" + RelativeUrl + ":" + AccessToken + ":"
 *                   + Lowercase(HexEncode(SHA-256(MinifyJson(RequestBody)))) + ":" + Timestamp
 *      Asymmetric (tanpa AccessToken):
 *      StringToSign = HTTPMethod + ":" + RelativeUrl + ":"
 *                   + Lowercase(HexEncode(SHA-256(MinifyJson(RequestBody)))) + ":" + Timestamp
 *
 * Timestamp format ISO-8601: YYYY-MM-DDThh:mm:ssTZD (contoh: 2024-07-16T19:50:23+07:00).
 */
final class SignatureService
{
    public const METHOD_SYMMETRIC = 'symmetric';
    public const METHOD_ASYMMETRIC = 'asymmetric';

    public function method(): string
    {
        $method = strtolower((string) Config::get('BANK_JATIM_SIGNATURE_METHOD', self::METHOD_SYMMETRIC));
        if (!in_array($method, [self::METHOD_SYMMETRIC, self::METHOD_ASYMMETRIC], true)) {
            throw new \RuntimeException(
                "BANK_JATIM_SIGNATURE_METHOD tidak valid: '{$method}'. Gunakan 'symmetric' atau 'asymmetric'."
            );
        }
        return $method;
    }

    public function algorithmName(): string
    {
        return $this->method() === self::METHOD_SYMMETRIC
            ? 'HMAC-SHA512 (base64)'
            : 'SHA256withRSA (base64)';
    }

    /** Timestamp ISO-8601 dengan timezone (server timezone Asia/Jakarta). */
    public function timestamp(): string
    {
        return date('Y-m-d\TH:i:sP');
    }

    /**
     * Minify JSON: menghapus seluruh whitespace. Body kosong -> string kosong.
     * Melempar exception bila input bukan JSON valid.
     */
    public function minifyJson(string $rawJson): string
    {
        $trimmed = trim($rawJson);
        if ($trimmed === '') {
            return '';
        }
        $decoded = json_decode($trimmed);
        if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
            throw new \InvalidArgumentException('Request body bukan JSON yang valid.');
        }
        $minified = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($minified === false) {
            throw new \InvalidArgumentException('Gagal melakukan minify JSON pada request body.');
        }
        return $minified;
    }

    /** Lowercase(HexEncode(SHA-256(minifiedBody))). */
    public function bodyHash(string $minifiedBody): string
    {
        return strtolower(hash('sha256', $minifiedBody));
    }

    /**
     * Signature Access Token.
     *
     * @return array{string_to_sign: string, signature: string, algorithm: string, timestamp: string}
     */
    public function signatureAccessToken(string $timestamp): array
    {
        $clientId = Config::require('BANK_JATIM_CLIENT_ID');
        $stringToSign = $clientId . '|' . $timestamp;

        return [
            'string_to_sign' => $stringToSign,
            'signature' => $this->sign($stringToSign),
            'algorithm' => $this->algorithmName(),
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Signature Service.
     *
     * @return array{string_to_sign: string, body_hash: string, signature: string, algorithm: string, timestamp: string}
     */
    public function signatureService(
        string $httpMethod,
        string $relativeUrl,
        string $accessToken,
        string $minifiedBody,
        string $timestamp
    ): array {
        $httpMethod = strtoupper(trim($httpMethod));
        $relativeUrl = trim($relativeUrl);
        if ($httpMethod === '' || $relativeUrl === '') {
            throw new \InvalidArgumentException('HTTP method dan relative URL wajib diisi.');
        }

        $bodyHash = $this->bodyHash($minifiedBody);

        if ($this->method() === self::METHOD_SYMMETRIC) {
            $stringToSign = $httpMethod . ':' . $relativeUrl . ':' . $accessToken
                . ':' . $bodyHash . ':' . $timestamp;
        } else {
            // Asymmetric: tanpa AccessToken pada StringToSign
            $stringToSign = $httpMethod . ':' . $relativeUrl . ':' . $bodyHash . ':' . $timestamp;
        }

        return [
            'string_to_sign' => $stringToSign,
            'body_hash' => $bodyHash,
            'signature' => $this->sign($stringToSign),
            'algorithm' => $this->algorithmName(),
            'timestamp' => $timestamp,
        ];
    }

    private function sign(string $stringToSign): string
    {
        if ($this->method() === self::METHOD_SYMMETRIC) {
            $secret = Config::require('BANK_JATIM_CLIENT_SECRET');
            return base64_encode(hash_hmac('sha512', $stringToSign, $secret, true));
        }
        return base64_encode($this->signWithPrivateKey($stringToSign));
    }

    private function signWithPrivateKey(string $data): string
    {
        $path = Config::require('BANK_JATIM_PRIVATE_KEY_PATH');
        $fullPath = str_starts_with($path, '/') ? $path : APP_ROOT . '/' . $path;

        if (!is_file($fullPath)) {
            throw new \RuntimeException("Private key tidak ditemukan: {$path}");
        }
        $keyContent = file_get_contents($fullPath);
        if ($keyContent === false || !str_contains($keyContent, '-----BEGIN')) {
            throw new \RuntimeException("Private key tidak dapat dibaca: {$path}");
        }

        $privateKey = openssl_pkey_get_private($keyContent);
        if ($privateKey === false) {
            throw new \RuntimeException('Private key tidak valid (format PKCS8 diharapkan).');
        }

        $signature = '';
        if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Gagal membuat signature RSA (SHA256withRSA).');
        }

        return $signature;
    }
}
