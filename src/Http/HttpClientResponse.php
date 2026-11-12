<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Hasil request HTTP dari HttpClient.
 */
final class HttpClientResponse
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $body,
        public readonly int $curlErrno,
        public readonly string $curlError,
        public readonly float $connectTimeMs,
        public readonly float $totalTimeMs,
    ) {
    }

    public static function fromCurlHandle(\CurlHandle $ch, string|bool $body): self
    {
        return new self(
            (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            is_string($body) ? $body : '',
            (int) curl_errno($ch),
            (string) curl_error($ch),
            (float) (curl_getinfo($ch, CURLINFO_CONNECT_TIME) * 1000),
            (float) (curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000),
        );
    }

    public static function fromCurlInitFailure(): self
    {
        return new self(0, '', -1, 'Failed to initialize cURL.', 0.0, 0.0);
    }

    /** Ada kegagalan di level koneksi (sebelum menerima HTTP response). */
    public function hasConnectionError(): bool
    {
        return $this->curlErrno !== 0 || $this->httpStatus === 0;
    }

    /** Decode body JSON; mengembalikan null bila bukan JSON valid. */
    public function jsonDecoded(): ?array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Klasifikasi error cURL untuk ditampilkan ke pengguna:
     * dns | tls | timeout | connection | other | none
     *
     * Nilai error code cURL bersifat ABI-stabil (curl.h); didefinisikan lokal
     * karena beberapa build libcurl tidak mengekspor konstanta CURLE_*.
     */
    private const ERR_COULDNT_RESOLVE_HOST = 6;
    private const ERR_COULDNT_CONNECT = 7;
    private const ERR_OPERATION_TIMEDOUT = 28;
    private const ERR_SSL_ERRORS = [35, 51, 58, 59, 77, 90]; // SSL connect, peer verification, cert problem, cipher, CA badfile, pinned pubkey mismatch

    public function errorCategory(): string
    {
        if ($this->curlErrno === 0) {
            return 'none';
        }
        if ($this->curlErrno === self::ERR_COULDNT_RESOLVE_HOST) {
            return 'dns';
        }
        if ($this->curlErrno === self::ERR_OPERATION_TIMEDOUT) {
            return 'timeout';
        }
        if (in_array($this->curlErrno, self::ERR_SSL_ERRORS, true)) {
            return 'tls';
        }
        if ($this->curlErrno === self::ERR_COULDNT_CONNECT) {
            return 'connection';
        }
        return 'other';
    }
}
