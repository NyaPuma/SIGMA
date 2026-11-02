<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketStatusEnum;
use App\Services\TicketStatusService;
use App\Traits\Auditable;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $opened_at
 * @property Carbon|null $in_progress_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $reopened_at
 * @property Carbon|null $assigned_at
 * @property Carbon|null $first_response_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $scheduled_end
 * @property Carbon|null $budget_requested_at
 * @property Carbon|null $budget_decided_at
 * @property Carbon|null $budget_approved_at
 * @property Carbon|null $due_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $reference
 * @property int|null $status_id
 * @property array<int, array<string, mixed>>|null $budget_details
 * @property-read User|null $user
 * @property-read User|null $technician
 * @property-read TicketStatus|null $status
 * @property-read Equipment|null $equipment
 * @property-read Room|null $room
 */
final class Ticket extends Model
{
    use Auditable;

    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function booted(): void
    {
        self::creating(function (Ticket $ticket) {
            if ($ticket->reference === null) {
                $ticket->reference = 'TKT-'.now()->format('YmdHis').'-'.strtoupper(substr(uniqid(), -5));
            }
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'title', 'description', 'priority', 'user_id', 'assigned_to',
        'reporter_name', 'reporter_contact', 'source',
        'equipment_id', 'room_id', 'status_id', 'reference', 'urgent',
        'opened_at', 'in_progress_at', 'closed_at', 'reopened_at',
        'assigned_at', 'first_response_at', 'resolved_at',
        'minutes_spent', 'technical_report', 'resolution_summary',
        'resolution', 'estimated_minutes', 'estimated_cost', 'actual_cost',
        'resolved_by', 'closed_by',
        'budget_requested', 'budget_status', 'budget_amount',
        'budget_requested_at', 'budget_approved_by', 'budget_decided_at',
        'budget_approved_at', 'budget_feedback', 'budget_details',
        'scheduled_at', 'scheduled_end', 'scheduled',
        'due_at', 'sla_breached',
        'recommended_technician_id', 'ai_recommendation_reason', 'ai_processed_at',
    ];

    /**
     * Attribute type casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'in_progress_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'assigned_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'scheduled_end' => 'datetime',
            'budget_requested_at' => 'datetime',
            'budget_decided_at' => 'datetime',
            'budget_approved_at' => 'datetime',
            'due_at' => 'datetime',
            'scheduled' => 'boolean',
            'budget_requested' => 'boolean',
            'urgent' => 'boolean',
            'sla_breached' => 'boolean',
            'actual_cost' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'budget_amount' => 'decimal:2',
            'estimated_minutes' => 'integer',
            'minutes_spent' => 'integer',
            'budget_details' => 'array',
            'ai_processed_at' => 'datetime',
        ];
    }

    // --- RELATIONSHIPS ---

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<TicketStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class);
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<User, $this> */
    public function budgetApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'budget_approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function recommendedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_technician_id');
    }

    /** @return HasMany<TicketWorkflowHistory, $this> */
    public function workflowHistory(): HasMany
    {
        return $this->hasMany(TicketWorkflowHistory::class);
    }

    /** @return HasMany<TicketComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /** @return HasMany<TicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    // --- SCOPES ---

    /** @param Builder<Ticket> $query
     *  @return Builder<Ticket> */
    public function scopeOpen(Builder $query): Builder
    {
        $statusId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);

        return $query->where('status_id', $statusId);
    }

    /** @param Builder<Ticket> $query
     *  @return Builder<Ticket> */
    public function scopeForTechnician(Builder $query, int $technicianId): Builder
    {
        return $query->where('assigned_to', $technicianId);
    }

    // --- ACCESSORS & HELPER METHODS ---

    /**
     * Calculate pause duration in minutes while awaiting budget approval.
     */
    /** @return Attribute<int, never> */
    protected function budgetPauseMinutes(): Attribute
    {
        return Attribute::make(
            get: fn (): int => ($this->budget_requested_at && $this->budget_decided_at)
                ? (int) $this->budget_requested_at->diffInMinutes($this->budget_decided_at)
                : 0
        );
    }

    public function hasStatus(TicketStatusEnum|string $status): bool
    {
        $statusEnum = is_string($status) ? TicketStatusEnum::tryFrom($status) : $status;

        if (! $statusEnum) {
            return false;
        }

        $statusId = app(TicketStatusService::class)->getByName($statusEnum);

        return $this->status_id === $statusId;
    }
}
