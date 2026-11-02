<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $created_at
 * @property-read TicketStatus|null $originStatus
 * @property-read TicketStatus|null $destinationStatus
 */
final class TicketWorkflowHistory extends Model
{
    // (no factory)

    protected $table = 'ticket_workflow_history';

    protected $fillable = [
        'ticket_id',
        'origin_status_id',
        'destination_status_id',
        'technician_id',
        'comment',
    ];

    // --- RELATIONSHIPS ---

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /** @return BelongsTo<TicketStatus, $this> */
    public function originStatus(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'origin_status_id');
    }

    /** @return BelongsTo<TicketStatus, $this> */
    public function destinationStatus(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'destination_status_id');
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    // --- SCOPES ---

    /**
     * Scope to order workflow history descending chronologically.
     */
    /** @param Builder<TicketWorkflowHistory> $query
     *  @return Builder<TicketWorkflowHistory> */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    // --- ACCESSORS ---

    /**
     * Text representation of the status transition (e.g. "Open ➔ In Progress").
     */
    /** @return Attribute<string, never> */
    protected function transitionLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => sprintf(
                '%s ➔ %s',
                (function () {
                    $n = $this->originStatus?->name;

                    return is_string($n) ? $n : 'N/A';
                })(),
                (function () {
                    $n = $this->destinationStatus?->name;

                    return is_string($n) ? $n : 'N/A';
                })()
            )
        );
    }

    /**
     * Relative time string.
     */
    /** @return Attribute<string, never> */
    protected function timeAgo(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->created_at?->diffForHumans() ?? ''
        );
    }
}
