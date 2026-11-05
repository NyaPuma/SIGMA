<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Equipment;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use RuntimeException;

final class QrCodeService
{
    /**
     * Public URL that the QR Code should encode.
     */
    public function urlFor(Equipment $equipment): string
    {
        return route('ticket.public.create', ['machine_id' => $equipment->id]);
    }

    /**
     * Generates the QR Code in PNG format (binary) for download.
     */
    public function png(Equipment $equipment): string
    {
        $result = (new PngWriter)->write(new QrCode($this->urlFor($equipment)));

        return $result->getString();
    }

    /**
     * Generates the QR Code as a Data URI (PNG) for embedding in <img> or PDF.
     */
    public function pngDataUri(Equipment $equipment): string
    {
        $result = (new PngWriter)->write(new QrCode($this->urlFor($equipment)));

        $dataUri = $result->getDataUri();

        if ($dataUri === '') {
            throw new RuntimeException('Could not generate QR Code in PNG format.');
        }

        return $dataUri;
    }
}
