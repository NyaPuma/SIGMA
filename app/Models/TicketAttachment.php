<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TicketAttachmentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $original_name
 * @property-read User|null $user
 */
final class TicketAttachment extends Model
{
    /** @use HasFactory<TicketAttachmentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'original_name',
        'file_name',
        'path',
        'disk',
        'extension',
        'mime_type',
        'size',
        'checksum',
        'description',
    ];

    /**
     * Attribute type casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $attachment): void {
            if ($attachment->original_name === null) {
                $attachment->original_name = $attachment->file_name ?? 'file_'.uniqid();
            }
        });

        // Ensure physical file is deleted from Storage on model deletion
        self::deleting(static function (self $attachment): void {
            $disk = $attachment->disk ?: 'public';
            if ($attachment->path && Storage::disk($disk)->exists($attachment->path)) {
                Storage::disk($disk)->delete($attachment->path);
            }
        });
    }

    // --- RELATIONSHIPS ---

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- ACCESSORS ---

    /**
     * Direct URL for file download or display.
     */
    /** @return Attribute<string, never> */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Storage::disk($this->disk ?: 'public')->url($this->path)
        );
    }

    /**
     * Formatted file size string (KB, MB, GB).
     */
    /** @return Attribute<string, never> */
    protected function formattedSize(): Attribute
    {
        return Attribute::make(
            get: fn (): string => self::formatBytes($this->size)
        );
    }

    /**
     * Format a byte count as a human-readable file size string.
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];

        $value = (float) $bytes;

        foreach ($units as $index => $unit) {
            if ($value < 1024 || $index === count($units) - 1) {
                $decimals = $value >= 100 || $value === floor($value) ? 0 : 1;

                return number_format($value, $decimals, '.', '').' '.$unit;
            }

            $value /= 1024;
        }

        return '0 B';
    }

    /**
     * Check if attachment is an image.
     */
    /** @return Attribute<bool, never> */
    protected function isImage(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => str_starts_with($this->mime_type ?? '', 'image/')
        );
    }
}
