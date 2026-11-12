<?php

declare(strict_types=1);

/**
 * Dashboard pengujian.
 *
 * @var array $data
 * @var array|null $flash hasil pengujian terakhir (session)
 * @var array $configSummary ringkasan konfigurasi
 * @var array $tokenStatus status token cache
 * @var array $latestByType log terakhir per jenis pengujian
 * @var array $latestLogs riwayat pengujian
 * @var bool $dbAvailable status koneksi database
 * @var array $qrDefaults nilai default formulir QR MPM
 */

$latestByType = $data['latest_by_type'];
$flash = $data['flash'];
$latestLog = $data['latest_logs'][0] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Bank Jatim SNAP API Tester</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <h1>Bank Jatim SNAP API Tester</h1>
        <form method="post" action="?action=logout" class="inline-form">
            <?= \App\Security\Auth::csrfField() ?>
            <button type="submit" class="btn btn-ghost">Logout</button>
        </form>
    </div>
</header>

<main class="container">

    <?php if (!$data['db_available']): ?>
        <div class="alert alert-warning">
            Database tidak dapat diakses — riwayat pengujian tidak akan tersimpan.
            Pastikan MySQL berjalan dan konfigurasi DB pada .env benar.
        </div>
    <?php endif; ?>

    <!-- ===== Ringkasan Konfigurasi ===== -->
    <section class="panel">
        <h2>Konfigurasi</h2>
        <table class="kv-table">
            <tr>
                <th>Base URL</th>
                <td>
                    <?php if ($data['config_summary']['base_url'] !== ''): ?>
                        <code><?= e($data['config_summary']['base_url']) ?></code>
                    <?php else: ?>
                        <span class="badge badge-warning">belum diisi di .env</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Client ID</th>
                <td>
                    <?php if ($data['config_summary']['client_id_set']): ?>
                        <span class="badge badge-success">terisi</span>
                    <?php else: ?>
                        <span class="badge badge-warning">belum diisi di .env</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Client Secret</th>
                <td>
                    <?php if ($data['config_summary']['client_secret_set']): ?>
                        <span class="badge badge-success">terisi</span>
                    <?php else: ?>
                        <span class="badge badge-warning">belum diisi di .env</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Metode Signature</th>
                <td><span class="badge"><?= e($data['config_summary']['signature_method']) ?></span></td>
            </tr>
            <tr>
                <th>Partner ID</th>
                <td>
                    <?php if ($data['config_summary']['partner_id_set']): ?>
                        <span class="badge badge-success">terisi</span>
                    <?php else: ?>
                        <span class="badge badge-warning">belum diisi di .env</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Database</th>
                <td>
                    <?php if ($data['db_available']): ?>
                        <span class="badge badge-success">terhubung</span>
                    <?php else: ?>
                        <span class="badge badge-error">tidak terhubung</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </section>

    <!-- ===== Status ===== -->
    <section class="status-grid">
        <div class="card">
            <h3>Connection Status</h3>
            <?php $c = $latestByType['connectivity']; ?>
            <?php if ($c !== null): ?>
                <p class="status <?= $c['status'] === 'success' ? 'status-success' : 'status-failed' ?>">
                    <?= $c['status'] === 'success' ? 'Terhubung' : 'Gagal' ?>
                </p>
                <p class="muted small">
                    <?php if ($c['http_status'] !== null): ?>HTTP <?= (int) $c['http_status'] ?> &middot; <?php endif; ?>
                    <?= e((string) $c['created_at']) ?>
                </p>
            <?php else: ?>
                <p class="status">-</p>
                <p class="muted small">Belum pernah diuji</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Token Status</h3>
            <?php if ($data['token_status']['state'] === 'active'): ?>
                <p class="status status-success">Aktif</p>
                <p class="muted small">
                    Berlaku <?= (int) $data['token_status']['remaining_seconds'] ?> detik lagi
                    (<?= e($data['token_status']['token_type']) ?>)
                </p>
            <?php elseif ($data['token_status']['state'] === 'expired'): ?>
                <p class="status status-warning">Expired</p>
                <p class="muted small">Token cache sudah kedaluwarsa — jalankan Get Token.</p>
            <?php else: ?>
                <p class="status">Belum ada</p>
                <p class="muted small">Jalankan Get Token untuk autentikasi.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Signature Status</h3>
            <?php $s = $latestByType['signature']; ?>
            <?php if ($s !== null): ?>
                <p class="status <?= $s['status'] === 'success' ? 'status-success' : 'status-failed' ?>">
                    <?= $s['status'] === 'success' ? 'Berhasil dibuat' : 'Gagal' ?>
                </p>
                <p class="muted small"><?= e((string) $s['created_at']) ?></p>
            <?php else: ?>
                <p class="status">-</p>
                <p class="muted small">Belum pernah diuji</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Last Test</h3>
            <?php if ($latestLog !== null): ?>
                <p class="status"><?= e(type_label($latestLog['test_type'])) ?></p>
                <p class="muted small"><?= e((string) $latestLog['created_at']) ?></p>
            <?php else: ?>
                <p class="status">-</p>
                <p class="muted small">Belum ada pengujian</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Response Time</h3>
            <?php if ($latestLog !== null && $latestLog['response_time_ms'] !== null): ?>
                <p class="status"><?= (int) $latestLog['response_time_ms'] ?> ms</p>
            <?php else: ?>
                <p class="status">-</p>
            <?php endif; ?>
        </div>

        <div class="card card-wide">
            <h3>Error Information</h3>
            <?php if ($latestLog !== null && $latestLog['error_message'] !== null && $latestLog['error_message'] !== ''): ?>
                <p class="status status-failed small"><?= e($latestLog['error_message']) ?></p>
            <?php else: ?>
                <p class="muted small">Tidak ada error pada pengujian terakhir.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- ===== Hasil Pengujian ===== -->
    <?php if ($flash !== null): ?>
        <section class="panel">
            <h2>Hasil: <?= e(type_label($flash['test_type'] ?? '')) ?>
                <?php if (($flash['status'] ?? '') === 'success'): ?>
                    <span class="badge badge-success">sukses</span>
                <?php else: ?>
                    <span class="badge badge-error">gagal</span>
                <?php endif; ?>
            </h2>

            <?php if (!empty($flash['error_message'])): ?>
                <div class="alert alert-error"><?= e($flash['error_message']) ?></div>
            <?php endif; ?>

            <table class="kv-table">
                <?php if (!empty($flash['endpoint'])): ?>
                    <tr><th>Endpoint</th><td><code><?= e($flash['endpoint']) ?></code></td></tr>
                <?php endif; ?>
                <?php if (!empty($flash['http_method'])): ?>
                    <tr><th>HTTP Method</th><td><?= e($flash['http_method']) ?></td></tr>
                <?php endif; ?>
                <?php if (($flash['http_status'] ?? null) !== null): ?>
                    <tr><th>HTTP Status</th><td><?= (int) $flash['http_status'] ?></td></tr>
                <?php endif; ?>
                <?php if (($flash['response_time_ms'] ?? null) !== null): ?>
                    <tr><th>Response Time</th><td><?= (int) $flash['response_time_ms'] ?> ms</td></tr>
                <?php endif; ?>
                <?php if (!empty($flash['connect_time_ms'])): ?>
                    <tr><th>Connect Time</th><td><?= (int) $flash['connect_time_ms'] ?> ms</td></tr>
                <?php endif; ?>
                <?php if (!empty($flash['from_cache'])): ?>
                    <tr><th>Catatan</th><td>Token diambil dari cache (masih berlaku).</td></tr>
                <?php endif; ?>
                <?php if (!empty($flash['token_info']) && is_array($flash['token_info']) && !empty($flash['token_info']['token_type'])): ?>
                    <tr>
                        <th>Token</th>
                        <td>
                            <?= e((string) $flash['token_info']['token_type']) ?> &middot;
                            berlaku <?= (int) $flash['token_info']['expires_in'] ?> detik
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($flash['response_payload'])): ?>
                    <tr>
                        <th>Response Payload</th>
                        <td>
                            <details>
                                <summary>Tampilkan response (sanitized)</summary>
                                <pre class="code-block"><?= e(json_pretty($flash['response_payload'])) ?></pre>
                            </details>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>

            <?php if (!empty($flash['access_token_signature'])): ?>
                <h3>Signature Access Token</h3>
                <table class="kv-table">
                    <tr><th>Algorithm</th><td><?= e($flash['access_token_signature']['algorithm']) ?></td></tr>
                    <tr><th>Timestamp</th><td><?= e($flash['access_token_signature']['timestamp']) ?></td></tr>
                    <tr><th>StringToSign</th><td><code><?= e($flash['access_token_signature']['string_to_sign']) ?></code></td></tr>
                    <tr><th>X-SIGNATURE</th><td><code class="break-all"><?= e($flash['access_token_signature']['signature']) ?></code></td></tr>
                </table>
            <?php endif; ?>

            <?php if (!empty($flash['service_signature'])): ?>
                <h3>Signature Service</h3>
                <table class="kv-table">
                    <tr><th>Algorithm</th><td><?= e($flash['service_signature']['algorithm']) ?></td></tr>
                    <tr><th>Body Hash</th><td><code><?= e($flash['service_signature']['body_hash']) ?></code></td></tr>
                    <tr><th>StringToSign</th><td><code class="break-all"><?= e($flash['service_signature']['string_to_sign']) ?></code></td></tr>
                    <tr><th>X-SIGNATURE</th><td><code class="break-all"><?= e($flash['service_signature']['signature']) ?></code></td></tr>
                </table>
            <?php endif; ?>

            <?php if (!empty($flash['qr_content'])): ?>
                <h3>QR Content</h3>
                <div class="qr-row">
                    <pre class="code-block qr-content" id="qr-content"><?= e($flash['qr_content']) ?></pre>
                    <button type="button" class="btn btn-small" data-copy-target="qr-content">Salin</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($flash['request_payload'])): ?>
                <details>
                    <summary>Request payload (sanitized)</summary>
                    <pre class="code-block"><?= e(json_pretty($flash['request_payload'])) ?></pre>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- ===== Aksi Pengujian ===== -->
    <section class="panel">
        <h2>Jalankan Pengujian</h2>

        <div class="action-row">
            <form method="post" action="?action=connectivity" class="inline-form js-submit-once">
                <?= \App\Security\Auth::csrfField() ?>
                <button type="submit" class="btn btn-primary">Uji Konektivitas</button>
                <span class="muted small">GET ke Base URL (cek DNS, TCP, TLS).</span>
            </form>

            <form method="post" action="?action=get_token" class="inline-form js-submit-once">
                <?= \App\Security\Auth::csrfField() ?>
                <button type="submit" class="btn btn-primary">Get Access Token</button>
                <span class="muted small">Autentikasi B2B (selalu meminta token baru).</span>
            </form>
        </div>

        <form method="post" action="?action=signature_test" class="test-form js-submit-once">
            <?= \App\Security\Auth::csrfField() ?>
            <h3>Signature Test</h3>
            <p class="muted small">
                Membuat Signature Access Token dan Signature Service secara lokal
                (tanpa mengirim request). Untuk metode symmetric, signature service
                membutuhkan access token — jalankan Get Access Token terlebih dahulu.
            </p>
            <div class="form-grid">
                <div>
                    <label for="sig-method">HTTP Method</label>
                    <select id="sig-method" name="http_method">
                        <?php foreach (['POST', 'PUT', 'DELETE', 'GET'] as $m): ?>
                            <option value="<?= $m ?>" <?= $m === 'POST' ? 'selected' : '' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="span-2">
                    <label for="sig-url">Relative URL</label>
                    <input type="text" id="sig-url" name="relative_url" value="/api/v1.0/qr/qr-mpm-generate">
                </div>
                <div class="span-2">
                    <label for="sig-body">Request Body (JSON, kosongkan bila tanpa body)</label>
                    <textarea id="sig-body" name="request_body" rows="6"
                              placeholder='{"partnerReferenceNo":"...","amount":{"value":"1000.00","currency":"IDR"},...}'></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Generate Signature</button>
        </form>

        <form method="post" action="?action=qr_generate" class="test-form js-submit-once">
            <?= \App\Security\Auth::csrfField() ?>
            <h3>QR MPM Generate (End-to-End)</h3>
            <p class="muted small">
                Alur lengkap: get access token &rarr; signature service &rarr;
                <code>POST <?= e(\App\Services\BankJatimService::QR_RELATIVE) ?></code>.
            </p>
            <div class="form-grid">
                <div>
                    <label for="qr-amount">Amount (IDR)</label>
                    <input type="number" id="qr-amount" name="amount" min="1" step="0.01" required
                           placeholder="1000.00">
                </div>
                <div>
                    <label for="qr-validity">Validity Period (ISO-8601, mendatang)</label>
                    <input type="text" id="qr-validity" name="validity_period" required
                           value="<?= e($data['qr_defaults']['validity_period']) ?>">
                </div>
                <div>
                    <label for="qr-merchant">Merchant ID</label>
                    <input type="text" id="qr-merchant" name="merchant_id" required
                           value="<?= e($data['qr_defaults']['merchant_id']) ?>">
                </div>
                <div>
                    <label for="qr-store">Store ID</label>
                    <input type="text" id="qr-store" name="store_id" required
                           value="<?= e($data['qr_defaults']['store_id']) ?>">
                </div>
                <div>
                    <label for="qr-terminal">Terminal ID</label>
                    <input type="text" id="qr-terminal" name="terminal_id" required
                           value="<?= e($data['qr_defaults']['terminal_id']) ?>">
                </div>
                <div>
                    <label for="qr-bill">Bill Number</label>
                    <input type="text" id="qr-bill" name="bill_number" required
                           value="<?= e($data['qr_defaults']['bill_number']) ?>">
                </div>
                <div>
                    <label for="qr-purpose">Transaction Purpose</label>
                    <input type="text" id="qr-purpose" name="transaction_purpose" required maxlength="15"
                           value="<?= e($data['qr_defaults']['transaction_purpose']) ?>">
                </div>
                <div>
                    <label for="qr-customer">Customer Label</label>
                    <input type="text" id="qr-customer" name="customer_label" required maxlength="15"
                           value="<?= e($data['qr_defaults']['customer_label']) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Generate QR MPM</button>
        </form>
    </section>

    <!-- ===== Riwayat Pengujian ===== -->
    <section class="panel">
        <h2>Riwayat Pengujian</h2>
        <?php if ($data['latest_logs'] === []): ?>
            <p class="muted">Belum ada riwayat pengujian.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="log-table">
                    <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Jenis</th>
                        <th>Endpoint</th>
                        <th>Method</th>
                        <th>HTTP</th>
                        <th>Waktu (ms)</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data['latest_logs'] as $log): ?>
                        <tr>
                            <td class="nowrap"><?= e((string) $log['created_at']) ?></td>
                            <td><?= e(type_label($log['test_type'])) ?></td>
                            <td class="endpoint-cell"><code><?= e((string) $log['endpoint']) ?></code></td>
                            <td><?= e((string) $log['http_method']) ?></td>
                            <td><?= $log['http_status'] !== null ? (int) $log['http_status'] : '-' ?></td>
                            <td><?= $log['response_time_ms'] !== null ? (int) $log['response_time_ms'] : '-' ?></td>
                            <td>
                                <?php if ($log['status'] === 'success'): ?>
                                    <span class="badge badge-success">sukses</span>
                                <?php else: ?>
                                    <span class="badge badge-error">gagal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <details>
                                    <summary>Lihat</summary>
                                    <?php if (!empty($log['error_message'])): ?>
                                        <p class="small"><strong>Error:</strong> <?= e($log['error_message']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($log['request_payload'] !== null): ?>
                                        <p class="small"><strong>Request:</strong></p>
                                        <pre class="code-block"><?= e(json_pretty($log['request_payload'])) ?></pre>
                                    <?php endif; ?>
                                    <?php if ($log['response_payload'] !== null): ?>
                                        <p class="small"><strong>Response:</strong></p>
                                        <pre class="code-block"><?= e(json_pretty($log['response_payload'])) ?></pre>
                                    <?php endif; ?>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</main>

<footer class="container footer">
    <p class="muted small">
        Bank Jatim SNAP API Integration Tester &middot; PHP Native + MySQL &middot;
        Spesifikasi: JatimAPI - SNAP v1.9.4
    </p>
</footer>

<script src="assets/js/app.js"></script>
</body>
</html>
