<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use App\Helpers\Logger;
use App\Helpers\ResponseHelper;
use App\Http\HttpClient;
use App\Http\HttpClientResponse;

/**
 * Proses Access Token B2B sesuai SNAP v1.9.4:
 *
 *   POST <domain>/access_token/v1.0/b2b
 *   Headers: Content-Type, X-TIMESTAMP, X-CLIENT-KEY (client_id), X-SIGNATURE
 *   Body   : {"grantType":"client_credentials","additionalInfo":{}}
 *
 * Token yang berhasil disimpan di memory (PHP session) selama pengujian —
 * TIDAK disimpan plaintext ke database.
 */
final class TokenService
{
    public const ENDPOINT_RELATIVE = '/access_token/v1.0/b2b';
    public const SUCCESS_CODE = '2007300';

    private const SESSION_KEY = 'access_token';
    private const EXPIRY_MARGIN_SECONDS = 60;

    public function __construct(
        private readonly HttpClient $httpClient = new HttpClient(),
        private readonly SignatureService $signature = new SignatureService(),
    ) {
    }

    /** Info token yang tersimpan di session (tanpa validasi expiry). */
    public function cacheInfo(): ?array
    {
        $cached = $_SESSION[self::SESSION_KEY] ?? null;
        return is_array($cached) ? $cached : null;
    }

    /** Cek apakah token cache sudah benar-benar expired (tanpa margin). */
    public function isExpired(?array $cache): bool
    {
        if ($cache === null) {
            return true;
        }
        $issuedAt = (int) ($cache['issued_at'] ?? 0);
        $expiresIn = (int) ($cache['expires_in'] ?? 0);
        if ($issuedAt <= 0 || $expiresIn <= 0) {
            return true;
        }
        return time() >= $issuedAt + $expiresIn;
    }

    /**
     * Token valid dari cache (dengan margin aman), atau null bila tidak ada
     * atau sudah mendekati expired.
     */
    public function cachedToken(): ?array
    {
        $cached = $this->cacheInfo();
        if ($cached === null) {
            return null;
        }
        $issuedAt = (int) ($cached['issued_at'] ?? 0);
        $expiresIn = (int) ($cached['expires_in'] ?? 0);
        if ($issuedAt <= 0 || $expiresIn <= 0) {
            return null;
        }
        if (time() >= $issuedAt + $expiresIn - self::EXPIRY_MARGIN_SECONDS) {
            return null;
        }
        return $cached;
    }

    /**
     * Mengambil access token dari Bank Jatim.
     * Memakai cache bila tersedia, kecuali $force = true.
     *
     * @return array hasil terstruktur untuk ditampilkan/dicatat
     */
    public function fetchToken(bool $force = false): array
    {
        $cached = $this->cachedToken();
        if (!$force && $cached !== null) {
            return $this->cachedResult($cached);
        }

        $result = [
            'test_type' => 'access_token',
            'endpoint' => null,
            'http_method' => 'POST',
            'http_status' => null,
            'request_payload' => null,
            'response_payload' => null,
            'response_time_ms' => null,
            'status' => 'failed',
            'error_message' => null,
            'token_info' => null,
            'signature_info' => null,
            'from_cache' => false,
        ];

        try {
            $baseUrl = Config::require('BANK_JATIM_BASE_URL');
            $clientId = Config::require('BANK_JATIM_CLIENT_ID');
            // Client secret (symmetric) atau private key (asymmetric) divalidasi
            // di dalam SignatureService::sign().

            $timestamp = $this->signature->timestamp();
            $signed = $this->signature->signatureAccessToken($timestamp);

            $url = rtrim($baseUrl, '/') . self::ENDPOINT_RELATIVE;
            $body = '{"grantType":"client_credentials","additionalInfo":{}}';
            $headers = [
                'Content-Type: application/json',
                'X-TIMESTAMP: ' . $timestamp,
                'X-CLIENT-KEY: ' . $clientId,
                'X-SIGNATURE: ' . $signed['signature'],
            ];

            Logger::info('Access token request: ' . self::ENDPOINT_RELATIVE);

            $response = $this->httpClient->request(
                'POST',
                $url,
                $headers,
                $body,
                Config::int('BANK_JATIM_TIMEOUT', 30)
            );

            return $this->processResponse($url, $signed, $response);
        } catch (\Throwable $e) {
            $result['error_message'] = $e->getMessage();
            Logger::warning('Access token request error: ' . $e->getMessage());
            return $result;
        }
    }

    private function processResponse(string $url, array $signed, HttpClientResponse $response): array
    {
        $result = [
            'test_type' => 'access_token',
            'endpoint' => $url,
            'http_method' => 'POST',
            'http_status' => $response->httpStatus,
            'request_payload' => ['grantType' => 'client_credentials', 'additionalInfo' => new \stdClass()],
            'response_payload' => null,
            'response_time_ms' => round($response->totalTimeMs),
            'status' => 'failed',
            'error_message' => null,
            'token_info' => null,
            'signature_info' => $signed,
            'from_cache' => false,
        ];

        if ($response->hasConnectionError()) {
            $result['error_message'] = 'Koneksi gagal (' . $response->errorCategory() . '): ' . $response->curlError;
            return $result;
        }

        $decoded = $response->jsonDecoded();
        $result['response_payload'] = ResponseHelper::sanitizeAssoc($decoded);

        if ($decoded === null) {
            $result['error_message'] = 'Response bukan JSON yang valid (HTTP ' . $response->httpStatus . ').';
            return $result;
        }

        $code = (string) ($decoded['responseCode'] ?? '');

        if ($response->httpStatus === 200 && $code === self::SUCCESS_CODE) {
            $accessToken = (string) ($decoded['accessToken'] ?? '');
            $expiresIn = (int) ($decoded['expiresIn'] ?? 0);

            if ($accessToken === '' || $expiresIn <= 0) {
                $result['error_message'] = 'Response token tidak valid: field wajib accessToken/expiresIn tidak ada.';
                return $result;
            }

            $token = [
                'access_token' => $accessToken,
                'token_type' => (string) ($decoded['tokenType'] ?? 'Bearer'),
                'expires_in' => $expiresIn,
                'issued_at' => time(),
            ];
            // Simpan di memory (session), bukan database.
            $_SESSION[self::SESSION_KEY] = $token;

            $result['status'] = 'success';
            $result['token_info'] = $token;
            return $result;
        }

        // HTTP 200 tetapi business response gagal, atau HTTP error.
        $message = (string) ($decoded['responseMessage'] ?? '');
        $result['error_message'] = sprintf(
            'Token gagal (HTTP %d, responseCode %s): %s',
            $response->httpStatus,
            $code !== '' ? $code : '-',
            $message !== '' ? $message : 'tidak ada keterangan'
        );
        return $result;
    }

    private function cachedResult(array $cached): array
    {
        return [
            'test_type' => 'access_token',
            'endpoint' => '(cache)',
            'http_method' => 'POST',
            'http_status' => null,
            'request_payload' => null,
            'response_payload' => null,
            'response_time_ms' => null,
            'status' => 'success',
            'error_message' => null,
            'token_info' => $cached,
            'signature_info' => null,
            'from_cache' => true,
        ];
    }
}
