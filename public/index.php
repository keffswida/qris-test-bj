<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Config;
use App\Database\Database;
use App\Helpers\ResponseHelper;
use App\Repositories\ApiTestLogRepository;
use App\Security\Auth;
use App\Services\BankJatimService;
use App\Services\SignatureService;
use App\Services\TokenService;

require_once dirname(__DIR__) . '/src/Views/helpers.php';

/**
 * Simpan hasil pengujian ke riwayat (payload harus sudah disanitasi).
 * Kegagalan DB tidak menutupi hasil komunikasi API.
 */
function saveLog(array $result): void
{
    (new ApiTestLogRepository())->save([
        'test_type' => $result['test_type'] ?? 'unknown',
        'endpoint' => $result['endpoint'] ?? null,
        'http_method' => $result['http_method'] ?? null,
        'http_status' => $result['http_status'] ?? null,
        'request_payload' => $result['request_payload'] ?? null,
        'response_payload' => $result['response_payload'] ?? null,
        'response_time_ms' => $result['response_time_ms'] ?? null,
        'status' => $result['status'] ?? 'failed',
        'error_message' => $result['error_message'] ?? null,
    ]);
}

/**
 * Signature test: membuat kedua jenis signature secara lokal.
 * Signature asli hanya ditampilkan di layar; versi yang dicatat ke riwayat
 * disamarkan (overview.md §4.5: signature tidak disimpan plaintext).
 */
function runSignatureTest(array $post): array
{
    $signature = new SignatureService();

    $result = [
        'test_type' => 'signature',
        'endpoint' => null,
        'http_method' => null,
        'http_status' => null,
        'request_payload' => null,
        'response_payload' => null,
        'response_time_ms' => null,
        'status' => 'failed',
        'error_message' => null,
        'access_token_signature' => null,
        'service_signature' => null,
    ];

    try {
        $timestamp = $signature->timestamp();
        $result['access_token_signature'] = $signature->signatureAccessToken($timestamp);

        $method = strtoupper(trim((string) ($post['http_method'] ?? 'POST')));
        $relativeUrl = trim((string) ($post['relative_url'] ?? BankJatimService::QR_RELATIVE));
        $rawBody = (string) ($post['request_body'] ?? '');

        $minifiedBody = $signature->minifyJson($rawBody);

        // Signature service symmetric membutuhkan access token.
        $accessToken = '';
        if ($signature->method() === SignatureService::METHOD_SYMMETRIC) {
            $token = (new TokenService())->cachedToken();
            if ($token === null) {
                throw new \RuntimeException(
                    'Signature service (metode symmetric) membutuhkan access token. '
                    . 'Jalankan "Get Access Token" terlebih dahulu.'
                );
            }
            $accessToken = (string) $token['access_token'];
        }

        $result['service_signature'] = $signature->signatureService(
            $method,
            $relativeUrl,
            $accessToken,
            $minifiedBody,
            $timestamp
        );

        $result['status'] = 'success';
        $result['http_method'] = $method;
        $result['endpoint'] = $relativeUrl;

        $decodedBody = json_decode($rawBody, true);
        $result['request_payload'] = ResponseHelper::sanitizeAssoc(
            is_array($decodedBody) ? $decodedBody : ($rawBody !== '' ? ['raw' => $rawBody] : null)
        );

        // Versi untuk riwayat: signature & stringToSign disamarkan.
        $result['response_payload'] = [
            'string_to_sign' => '[REDACTED]',
            'signature' => '[REDACTED]',
            'body_hash' => $result['service_signature']['body_hash'],
            'algorithm' => $result['service_signature']['algorithm'],
        ];

        return $result;
    } catch (\Throwable $e) {
        $result['error_message'] = $e->getMessage();
        return $result;
    }
}

function buildDashboardData(): array
{
    $repo = new ApiTestLogRepository();
    $latestByType = [];
    foreach (['connectivity', 'access_token', 'signature', 'qr_mpm_generate'] as $type) {
        $latestByType[$type] = $repo->latestByType($type);
    }

    $tokenService = new TokenService();
    $cache = $tokenService->cacheInfo();
    $tokenStatus = ['state' => 'none', 'remaining_seconds' => 0, 'token_type' => 'Bearer'];
    if ($cache !== null) {
        if ($tokenService->isExpired($cache)) {
            $tokenStatus['state'] = 'expired';
        } else {
            $tokenStatus['state'] = 'active';
            $tokenStatus['remaining_seconds'] = max(
                0,
                (int) $cache['issued_at'] + (int) $cache['expires_in'] - time()
            );
        }
        $tokenStatus['token_type'] = (string) ($cache['token_type'] ?? 'Bearer');
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return [
        'db_available' => Database::isAvailable(),
        'latest_logs' => $repo->latest(50),
        'latest_by_type' => $latestByType,
        'token_status' => $tokenStatus,
        'flash' => is_array($flash) ? $flash : null,
        'config_summary' => [
            'base_url' => (string) Config::get('BANK_JATIM_BASE_URL', ''),
            'client_id_set' => Config::get('BANK_JATIM_CLIENT_ID') !== null,
            'client_secret_set' => Config::get('BANK_JATIM_CLIENT_SECRET') !== null,
            'partner_id_set' => Config::get('BANK_JATIM_PARTNER_ID') !== null,
            'signature_method' => Config::get('BANK_JATIM_SIGNATURE_METHOD', 'symmetric'),
        ],
        'qr_defaults' => [
            'amount' => '',
            'merchant_id' => (string) Config::get('BANK_JATIM_MERCHANT_ID', ''),
            'store_id' => (string) Config::get('BANK_JATIM_STORE_ID', ''),
            'terminal_id' => (string) Config::get('BANK_JATIM_TERMINAL_ID', ''),
            'validity_period' => date('Y-m-d\TH:i:sP', time() + 300),
            'bill_number' => date('YmdHis'),
            'transaction_purpose' => 'TEST BILL',
            'customer_label' => 'TEST CUSTOMER',
        ],
    ];
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'test_type' => 'notice',
        'status' => $type,
        'error_message' => $message,
    ];
}

// ============================ ROUTING ============================

$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Login (satu-satunya aksi POST yang boleh tanpa autentikasi) ---
    if ($action === 'login') {
        if (!Auth::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $error = 'Sesi kedaluwarsa. Silakan ulangi.';
            require dirname(__DIR__) . '/src/Views/login.php';
            exit;
        }
        if (Auth::attempt((string) ($_POST['password'] ?? ''))) {
            redirect('?');
        }
        $error = 'Password salah.';
        require dirname(__DIR__) . '/src/Views/login.php';
        exit;
    }

    if (!Auth::check()) {
        http_response_code(401);
        $error = 'Silakan login terlebih dahulu.';
        require dirname(__DIR__) . '/src/Views/login.php';
        exit;
    }

    if (!Auth::verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        setFlash('failed', 'Sesi kedaluwarsa atau CSRF token tidak valid. Silakan ulangi aksi.');
        redirect('?');
    }

    switch ($action) {
        case 'logout':
            Auth::logout();
            redirect('?');

        case 'connectivity':
            $result = (new BankJatimService())->connectivityTest();
            break;

        case 'get_token':
            $result = (new TokenService())->fetchToken(true);
            break;

        case 'signature_test':
            $result = runSignatureTest($_POST);
            break;

        case 'qr_generate':
            $result = (new BankJatimService())->generateQrMpm($_POST);
            break;

        default:
            setFlash('failed', 'Aksi tidak dikenal.');
            redirect('?');
    }

    saveLog($result);
    $_SESSION['flash'] = $result;
    redirect('?');
}

// --- GET ---
if (!Auth::check()) {
    $error = '';
    require dirname(__DIR__) . '/src/Views/login.php';
    exit;
}

$data = buildDashboardData();
require dirname(__DIR__) . '/src/Views/dashboard.php';
