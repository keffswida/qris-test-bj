<?php

declare(strict_types=1);

use App\Helpers\ResponseHelper;

/**
 * Escape output HTML untuk mencegah XSS (overview.md §9).
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Render payload (sudah disanitasi) sebagai JSON pretty.
 */
function json_pretty(mixed $value): string
{
    return ResponseHelper::prettyJson($value);
}

function type_label(?string $type): string
{
    return match ($type) {
        'connectivity' => 'Connectivity Test',
        'access_token' => 'Get Access Token',
        'signature' => 'Signature Test',
        'qr_mpm_generate' => 'QR MPM Generate',
        default => (string) $type,
    };
}
