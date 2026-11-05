<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for ticket photo uploads.
 *
 * Both upload paths — the authenticated API (`UploadPhotoRequest`) and the public
 * QR-code report (`PublicStoreTicketRequest`) — validate against these limits, and
 * the controllers store files with the extension resolved here, so a photo accepted
 * by one path is stored identically by the other.
 */
final class PhotoUploadPolicy
{
    /**
     * Used when `services.custom.upload.allowed_photo_mimes` is empty or unusable.
     *
     * @var list<string>
     */
    private const DEFAULT_TYPES = ['jpeg', 'jpg', 'png', 'gif', 'webp'];

    private const DEFAULT_MAX_KB = 2048;

    private const DEFAULT_MAX_EDGE_PX = 4096;

    /**
     * Extensions for the detected MIME types stored under a known name.
     *
     * @var array<string, string>
     */
    private const EXTENSION_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/tiff' => 'tiff',
        'image/avif' => 'avif',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];

    /**
     * Accepted image types, as `File::types()` aliases (extension names, not MIMEs).
     *
     * @return list<string>
     */
    public static function allowedTypes(): array
    {
        $configured = config('services.custom.upload.allowed_photo_mimes', []);

        if (! is_array($configured)) {
            return self::DEFAULT_TYPES;
        }

        $types = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $type): string => is_string($type) ? strtolower(trim($type)) : '',
                $configured
            ),
            static fn (string $type): bool => $type !== ''
        )));

        return $types === [] ? self::DEFAULT_TYPES : $types;
    }

    public static function maxSizeKb(): int
    {
        return self::positiveInt('services.custom.upload.max_photo_size_kb', self::DEFAULT_MAX_KB);
    }

    public static function maxWidth(): int
    {
        return self::positiveInt('services.custom.upload.max_photo_width', self::DEFAULT_MAX_EDGE_PX);
    }

    public static function maxHeight(): int
    {
        return self::positiveInt('services.custom.upload.max_photo_height', self::DEFAULT_MAX_EDGE_PX);
    }

    /**
     * Extension to store a detected MIME type under. Never rejects: a type that passed
     * validation must produce a file, so an unlisted image falls back to its sanitized
     * MIME subtype — and anything that is not an image, or has no detectable MIME at all,
     * to a neutral `img` — rather than being dropped.
     */
    public static function extensionFor(?string $mimeType): string
    {
        if ($mimeType === null) {
            return 'img';
        }

        $known = self::EXTENSION_BY_MIME[$mimeType] ?? null;

        if ($known !== null) {
            return $known;
        }

        if (! str_starts_with($mimeType, 'image/')) {
            return 'img';
        }

        $sanitized = preg_replace('/[^a-z0-9]/', '', substr($mimeType, strlen('image/'))) ?? '';

        return strtolower($sanitized) === '' ? 'img' : strtolower($sanitized);
    }

    /**
     * Reads a limit that must stay usable when the environment value is empty or bogus.
     */
    private static function positiveInt(string $key, int $fallback): int
    {
        $configured = config($key);

        return is_numeric($configured) && (int) $configured > 0 ? (int) $configured : $fallback;
    }
}
