<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string|null $code */
final class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function booted(): void
    {
        self::creating(function (TicketType $type) {
            if ($type->code === null) {
                $type->code = strtoupper(uniqid('TYPE_'));
            }
        });
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    // --- RELATIONSHIPS ---

    /** @return HasMany<TicketStatus, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(TicketStatus::class, 'type_id');
    }

    /**
     * Direct relationship to tickets associated with this type via statuses.
     */
    /** @return HasManyThrough<Ticket, TicketStatus, $this> */
    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(
            Ticket::class,
            TicketStatus::class,
            'type_id',   // Foreign key on ticket_statuses table
            'status_id'  // Foreign key on tickets table
        );
    }
}
