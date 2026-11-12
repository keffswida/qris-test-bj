<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use App\Helpers\Logger;
use App\Helpers\ResponseHelper;
use App\Http\HttpClient;

/**
 * Orkestrasi pengujian ke API Bank Jatim:
 * - connectivity test (TCP/TLS/DNS ke base URL)
 * - QR MPM Generate (end-to-end: access token + signature service + request)
 */
final class BankJatimService
{
    public const QR_RELATIVE = '/api/v1.0/qr/qr-mpm-generate';
    public const QR_SUCCESS_CODE = '2004700';
    public const QR_CHANNEL_ID = '00002';

    public function __construct(
        private readonly HttpClient $httpClient = new HttpClient(),
        private readonly SignatureService $signature = new SignatureService(),
        private readonly TokenService $tokenService = new TokenService(),
    ) {
    }

    /**
     * Connectivity test: GET ke base URL.
     * Keberhasilan koneksi TCP/TLS TIDAK berarti autentikasi API berhasil.
     */
    public function connectivityTest(): array
    {
        $result = [
            'test_type' => 'connectivity',
            'endpoint' => null,
            'http_method' => 'GET',
            'http_status' => null,
            'request_payload' => null,
            'response_payload' => null,
            'response_time_ms' => null,
            'status' => 'failed',
            'error_message' => null,
            'error_category' => 'none',
            'connect_time_ms' => null,
        ];

        $baseUrl = Config::get('BANK_JATIM_BASE_URL');
        if ($baseUrl === null) {
            $result['error_message'] = 'BANK_JATIM_BASE_URL belum diisi di .env.';
            return $result;
        }

        $result['endpoint'] = $baseUrl;
        Logger::info('Connectivity test: GET ' . $baseUrl);

        $response = $this->httpClient->request('GET', $baseUrl, [], null, 15);

        $result['response_time_ms'] = round($response->totalTimeMs);
        $result['connect_time_ms'] = round($response->connectTimeMs);
        $result['http_status'] = $response->httpStatus;
        $result['error_category'] = $response->errorCategory();

        if ($response->hasConnectionError()) {
            $category = $response->errorCategory();
            $labels = [
                'dns' => 'DNS resolution gagal',
                'tls' => 'Koneksi SSL/TLS gagal',
                'timeout' => 'Request timeout',
                'connection' => 'Koneksi TCP gagal',
                'other' => 'Kesalahan koneksi',
            ];
            $result['error_message'] = ($labels[$category] ?? 'Koneksi gagal') . ': ' . $response->curlError;
            Logger::warning('Connectivity test failed: ' . $result['error_message']);
            return $result;
        }

        // TCP/TLS berhasil; status HTTP berapa pun menunjukkan server merespons.
        $result['status'] = 'success';
        $result['response_payload'] = ResponseHelper::sanitizeAssoc([
            'note' => 'Koneksi berhasil. HTTP ' . $response->httpStatus . ' pada root URL bukan indikasi kegagalan —',
        ]);
        return $result;
    }

    /**
     * End-to-end test: MPM Generate QR MPM.
     * Meliputi get access token, signature service, dan pengiriman request.
     */
    public function generateQrMpm(array $input): array
    {
        $result = [
            'test_type' => 'qr_mpm_generate',
            'endpoint' => null,
            'http_method' => 'POST',
            'http_status' => null,
            'request_payload' => null,
            'response_payload' => null,
            'response_time_ms' => null,
            'status' => 'failed',
            'error_message' => null,
            'qr_content' => null,
            'signature_info' => null,
            'token_info' => null,
        ];

        try {
            $baseUrl = Config::require('BANK_JATIM_BASE_URL');

            $body = $this->buildQrBody($input);
            $decodedBody = json_decode($body, true);
            $result['request_payload'] = ResponseHelper::sanitizeAssoc(is_array($decodedBody) ? $decodedBody : $body);

            // 1) Access token (dari cache bila masih valid)
            $tokenResult = $this->tokenService->fetchToken();
            if ($tokenResult['status'] !== 'success' || empty($tokenResult['token_info'])) {
                $result['error_message'] = 'Gagal mendapatkan access token: '
                    . ($tokenResult['error_message'] ?? 'kondisi tidak diketahui');
                $result['token_info'] = ['status' => 'failed', 'error' => $result['error_message']];
                return $result;
            }
            $accessToken = (string) $tokenResult['token_info']['access_token'];
            $result['token_info'] = [
                'status' => $tokenResult['from_cache'] ? 'cached' : 'fresh',
                'token_type' => (string) ($tokenResult['token_info']['token_type'] ?? 'Bearer'),
                'expires_in' => (int) ($tokenResult['token_info']['expires_in'] ?? 0),
            ];

            // 2) Signature service
            $timestamp = $this->signature->timestamp();
            $signed = $this->signature->signatureService(
                'POST',
                self::QR_RELATIVE,
                $accessToken,
                $body,
                $timestamp
            );
            $result['signature_info'] = $signed;

            // 3) Kirim request
            $url = rtrim($baseUrl, '/') . self::QR_RELATIVE;
            $headers = $this->transactionHeaders($accessToken, $timestamp, $signed['signature']);

            Logger::info('QR MPM generate request: ' . self::QR_RELATIVE);

            $response = $this->httpClient->request(
                'POST',
                $url,
                $headers,
                $body,
                Config::int('BANK_JATIM_TIMEOUT', 30)
            );

            $result['endpoint'] = $url;
            $result['http_status'] = $response->httpStatus;
            $result['response_time_ms'] = round($response->totalTimeMs);

            if ($response->hasConnectionError()) {
                $result['error_message'] = 'Koneksi gagal (' . $response->errorCategory() . '): ' . $response->curlError;
                Logger::warning('QR MPM generate connection failed: ' . $result['error_message']);
                return $result;
            }

            $decoded = $response->jsonDecoded();
            $result['response_payload'] = ResponseHelper::sanitizeAssoc($decoded);

            if ($decoded === null) {
                $result['error_message'] = 'Response bukan JSON yang valid (HTTP ' . $response->httpStatus . ').';
                return $result;
            }

            $code = (string) ($decoded['responseCode'] ?? '');

            if ($response->httpStatus === 200 && $code === self::QR_SUCCESS_CODE) {
                $result['status'] = 'success';
                $result['qr_content'] = (string) ($decoded['qrContent'] ?? '');
                return $result;
            }

            $message = (string) ($decoded['responseMessage'] ?? '');
            $result['error_message'] = sprintf(
                'QR MPM gagal (HTTP %d, responseCode %s): %s',
                $response->httpStatus,
                $code !== '' ? $code : '-',
                $message !== '' ? $message : 'tidak ada keterangan'
            );
            return $result;
        } catch (\Throwable $e) {
            $result['error_message'] = $e->getMessage();
            Logger::warning('QR MPM generate error: ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * Build + validasi request body QR MPM (minified JSON string).
     */
    private function buildQrBody(array $input): string
    {
        $amount = trim((string) ($input['amount'] ?? ''));
        if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
            throw new \InvalidArgumentException('Nominal amount wajib diisi dengan angka lebih dari 0.');
        }

        $merchantId = $this->requireInput($input, 'merchant_id', 'Merchant ID');
        $storeId = $this->requireInput($input, 'store_id', 'Store ID');
        $terminalId = $this->requireInput($input, 'terminal_id', 'Terminal ID');
        $validityPeriod = $this->requireInput($input, 'validity_period', 'Validity Period');
        $billNumber = $this->requireInput($input, 'bill_number', 'Bill Number');
        $transactionPurpose = $this->requireInput($input, 'transaction_purpose', 'Transaction Purpose');
        $customerLabel = $this->requireInput($input, 'customer_label', 'Customer Label');

        $this->assertMaxLength($merchantId, 64, 'merchantId');
        $this->assertMaxLength($storeId, 20, 'storeId');
        $this->assertMaxLength($terminalId, 16, 'terminalId');
        $this->assertMaxLength($billNumber, 24, 'billNumber');
        $this->assertMaxLength($transactionPurpose, 15, 'transactionPurpose');
        $this->assertMaxLength($customerLabel, 15, 'customerLabel');

        // validityPeriod wajib > timestamp saat request (SNAP v1.9.4)
        $validityTs = strtotime($validityPeriod);
        if ($validityTs === false) {
            throw new \InvalidArgumentException('validityPeriod harus berformat ISO-8601, contoh: 2024-07-13T15:59:17+07:00');
        }
        if ($validityTs <= time()) {
            throw new \InvalidArgumentException('validityPeriod harus tanggal mendatang (lebih besar dari waktu sekarang).');
        }

        $body = [
            'partnerReferenceNo' => $this->newPartnerReferenceNo(),
            'amount' => [
                'value' => number_format((float) $amount, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'merchantId' => $merchantId,
            'storeId' => $storeId,
            'terminalId' => $terminalId,
            'validityPeriod' => $validityPeriod,
            'additionalInfo' => [
                'billNumber' => $billNumber,
                'transactionPurpose' => $transactionPurpose,
                'customerLabel' => $customerLabel,
            ],
        ];

        $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Gagal menyusun request body QR MPM.');
        }

        return $this->signature->minifyJson($encoded);
    }

    /**
     * Header transaksi web service sesuai SNAP v1.9.4 (bagian B).
     */
    private function transactionHeaders(string $accessToken, string $timestamp, string $signature): array
    {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'X-TIMESTAMP: ' . $timestamp,
            'X-SIGNATURE: ' . $signature,
            'X-PARTNER-ID: ' . Config::require('BANK_JATIM_PARTNER_ID'),
            'X-EXTERNAL-ID: ' . $this->newExternalId(),
            'CHANNEL-ID: ' . Config::get('BANK_JATIM_CHANNEL_ID', self::QR_CHANNEL_ID),
        ];
    }

    /** X-EXTERNAL-ID: unik per request per hari, maksimal 36 karakter. */
    public function newExternalId(): string
    {
        return date('YmdHis') . sprintf('%06d', random_int(0, 999999));
    }

    /** partnerReferenceNo: unik per request, maksimal 64 karakter. */
    public function newPartnerReferenceNo(): string
    {
        return date('YmdHis') . sprintf('%04d', random_int(0, 9999));
    }

    private function requireInput(array $input, string $key, string $label): string
    {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value === '') {
            throw new \InvalidArgumentException("{$label} wajib diisi.");
        }
        return $value;
    }

    private function assertMaxLength(string $value, int $max, string $field): void
    {
        if (mb_strlen($value) > $max) {
            throw new \InvalidArgumentException("{$field} melebihi panjang maksimal {$max} karakter.");
        }
    }
}
