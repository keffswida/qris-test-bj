<?php

declare(strict_types=1);

namespace App\Http;

/**
 * HTTP client berbasis PHP cURL.
 *
 * SSL verification selalu aktif (tidak boleh dinonaktifkan) dan redirect
 * tidak diikuti, sesuai ketentuan keamanan pada overview.md.
 */
final class HttpClient
{
    public const DEFAULT_CONNECT_TIMEOUT = 10;
    public const DEFAULT_TIMEOUT = 30;

    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeout = self::DEFAULT_TIMEOUT
    ): HttpClientResponse {
        $ch = curl_init();
        if ($ch === false) {
            return HttpClientResponse::fromCurlInitFailure();
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => strtoupper(trim($method)),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::DEFAULT_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'QRIS-BJ-Tester/1.0',
        ];

        if ($headers !== []) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);

        $responseBody = curl_exec($ch);
        $response = HttpClientResponse::fromCurlHandle($ch, $responseBody);
        curl_close($ch);

        return $response;
    }
}
