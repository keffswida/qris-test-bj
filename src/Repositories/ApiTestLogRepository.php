<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Helpers\Logger;
use App\Helpers\ResponseHelper;
use PDO;

/**
 * Penyimpanan riwayat pengujian ke tabel api_test_logs.
 * Seluruh nilai payload yang masuk harus SUDAH disanitasi oleh caller.
 */
final class ApiTestLogRepository
{
    public function save(array $log): bool
    {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO api_test_logs
                    (test_type, endpoint, http_method, http_status, request_payload,
                     response_payload, response_time_ms, status, error_message)
                 VALUES
                    (:test_type, :endpoint, :http_method, :http_status, :request_payload,
                     :response_payload, :response_time_ms, :status, :error_message)'
            );

            $stmt->bindValue(':test_type', (string) ($log['test_type'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':endpoint', isset($log['endpoint']) ? (string) $log['endpoint'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':http_method', isset($log['http_method']) ? (string) $log['http_method'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':http_status', isset($log['http_status']) ? (int) $log['http_status'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':request_payload', ResponseHelper::toJson($log['request_payload'] ?? null), PDO::PARAM_STR);
            $stmt->bindValue(':response_payload', ResponseHelper::toJson($log['response_payload'] ?? null), PDO::PARAM_STR);
            $stmt->bindValue(':response_time_ms', isset($log['response_time_ms']) ? (int) $log['response_time_ms'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':status', (string) ($log['status'] ?? 'failed'), PDO::PARAM_STR);
            $stmt->bindValue(':error_message', isset($log['error_message']) ? (string) $log['error_message'] : null, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (\Throwable $e) {
            // Kegagalan logging tidak boleh menutupi hasil komunikasi API.
            Logger::warning('Gagal menyimpan log pengujian: ' . $e->getMessage());
            return false;
        }
    }

    /** Riwayat terbaru (paling akhir di atas). */
    public function latest(int $limit = 50): array
    {
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT * FROM api_test_logs ORDER BY id DESC LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Log terakhir untuk satu jenis pengujian. */
    public function latestByType(string $type): ?array
    {
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT * FROM api_test_logs WHERE test_type = :type ORDER BY id DESC LIMIT 1'
            );
            $stmt->bindValue(':type', $type, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch();
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
