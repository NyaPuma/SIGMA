<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TicketCommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $edited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, self> $replies
 */
final class TicketComment extends Model
{
    /** @use HasFactory<TicketCommentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'parent_id',
        'comment',
        'is_internal',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'edited_at' => 'datetime',
        ];
    }

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

    /** @return BelongsTo<TicketComment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<TicketComment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // --- SCOPES ---

    /**
     * Scope to order comments chronologically.
     */
    /** @param Builder<TicketComment> $query
     *  @return Builder<TicketComment> */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'asc');
    }

    // --- ACCESSORS ---

    /**
     * Relative time string (e.g. "5 minutes ago").
     */
    /** @return Attribute<string, never> */
    protected function timeAgo(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->created_at?->diffForHumans() ?? ''
        );
    }

    /**
     * Check if the comment was edited after publishing.
     */
    /** @return Attribute<bool, never> */
    protected function isEdited(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->updated_at && $this->created_at && $this->updated_at->gt($this->created_at)
        );
    }
}
