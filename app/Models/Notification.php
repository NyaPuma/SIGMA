<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $read_at
 * @property Carbon|null $expires_at
 * @property-read User|null $user
 */
final class Notification extends Model
{
    // (no factory)
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'priority',
        'is_read',
        'read_at',
        'link',
        'notifiable_type',
        'notifiable_id',
        'data',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'expires_at' => 'datetime',
            'data' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter unread notifications.
     */
    /** @param Builder<Notification> $query
     *  @return Builder<Notification> */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to filter read notifications.
     */
    /** @param Builder<Notification> $query
     *  @return Builder<Notification> */
    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]);
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(): bool
    {
        return $this->update(['is_read' => false]);
    }
}
