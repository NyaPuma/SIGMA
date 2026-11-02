<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property array<string, mixed>|null $new_values
 * @property-read User|null $user
 */
final class Audit extends Model
{
    // (no factory)

    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'url',
        'ip_address',
        'user_agent',
    ];

    /**
     * Attribute type casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Prevent UPDATE or DELETE on immutable audit records.
     */
    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('Audit records are immutable and cannot be updated.');
        });

        self::deleting(static function (): void {
            throw new LogicException('Audit records are immutable and cannot be deleted.');
        });
    }

    /**
     * User who triggered the audited event.
     */
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Polymorphic model audited (e.g. Ticket, User, Equipment, etc.).
     */
    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
