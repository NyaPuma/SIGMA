<?php

namespace App\Enums;

enum FileTypeEnum: string
{
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';
    case Audio = 'audio';
    case Other = 'other';

    /**
     * Return all raw enum values in a simple array.
     */
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return the human-readable description for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Image => __('ticket_media.Imagem'),
            self::Document => __('common.Documento'),
            self::Video => __('common.Vídeo'),
            self::Audio => __('common.Áudio'),
            self::Other => __('common.Outro'),
        };
    }

    /**
     * Indicative icon for frontend visual representation.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Image => 'heroicon-o-photo',
            self::Document => 'heroicon-o-document-text',
            self::Video => 'heroicon-o-film',
            self::Audio => 'heroicon-o-speaker-wave',
            self::Other => 'heroicon-o-paper-clip',
        };
    }

    /**
     * Identify the FileTypeEnum based on MIME type.
     */
    public static function fromMimeType(string $mimeType): self
    {
        $mime = mb_strtolower(trim($mimeType));

        if (str_starts_with($mime, 'image/')) {
            return self::Image;
        }

        if (str_starts_with($mime, 'video/')) {
            return self::Video;
        }

        if (str_starts_with($mime, 'audio/')) {
            return self::Audio;
        }

        if (str_starts_with($mime, 'text/') || self::isDocumentMimeType($mime)) {
            return self::Document;
        }

        return self::Other;
    }

    /**
     * List of MIME types classified as documents.
     */
    private static function isDocumentMimeType(string $mime): bool
    {
        $documentMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
            'application/rtf',
            'application/csv',
            'application/json',
            'application/xml',
        ];

        return in_array($mime, $documentMimes, true);
    }

    /**
     * Safely normalize mixed input (string or enum instance).
     */
    public static function normalize(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return self::tryFrom(mb_strtolower(trim($value)));
    }
}
