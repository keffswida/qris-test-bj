-- ============================================================
-- Bank Jatim SNAP API Integration Tester — Schema
-- MySQL 8.0
-- ============================================================

CREATE TABLE IF NOT EXISTS api_test_logs (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    test_type        VARCHAR(50)     NOT NULL COMMENT 'Jenis pengujian: connectivity / access_token / signature / qr_mpm_generate',
    endpoint         TEXT            NULL COMMENT 'Endpoint tujuan',
    http_method      VARCHAR(10)     NULL COMMENT 'HTTP method',
    http_status      INT             NULL COMMENT 'HTTP status code',
    request_payload  JSON            NULL COMMENT 'Request payload yang sudah disanitasi',
    response_payload JSON            NULL COMMENT 'Response payload yang sudah disanitasi',
    response_time_ms INT             NULL COMMENT 'Durasi response (ms)',
    status           VARCHAR(20)     NOT NULL DEFAULT 'failed' COMMENT 'success / failed',
    error_message    TEXT            NULL COMMENT 'Informasi error',
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu pengujian',
    PRIMARY KEY (id),
    KEY idx_api_test_logs_type (test_type),
    KEY idx_api_test_logs_created_at (created_at)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
