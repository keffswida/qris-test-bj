<?php

declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Render QR String (payload EMVCo dari Bank Jatim) menjadi gambar PNG.
 *
 * Payload digunakan APA ADANYA — tidak diubah, tidak di-recalc CRC-nya.
 */
final class QrImageService
{
    public function dataUri(string $qrContent): string
    {
        if (trim($qrContent) === '') {
            throw new \InvalidArgumentException('QR content kosong, tidak bisa dirender.');
        }

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'scale' => 8,
            'margin' => 4,
            'eccLevel' => QRCode::ECC_M,
        ]);

        $png = (new QRCode($options))->render($qrContent);

        // render() mengembalikan data URI (data:image/png;base64,...) langsung.
        return str_starts_with($png, 'data:') ? $png : 'data:image/png;base64,' . base64_encode($png);
    }
}
