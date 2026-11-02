<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string|null $code */
final class Room extends Model
{
    use Auditable;

    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function booted(): void
    {
        self::creating(function (Room $room) {
            if ($room->code === null || trim((string) $room->code) === '') {
                $room->code = 'RM-'.strtoupper(uniqid());
            }
        });
    }

    protected $fillable = [
        'name',
        'code',
        'building',
        'floor',
        'location',
        'capacity',
        'description',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    /**
     * Equipment allocated to this room.
     */
    /** @return HasMany<Equipment, $this> */
    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /**
     * Maintenance tickets associated with this room.
     */
    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Scope to filter active rooms.
     */
    /** @param Builder<Room> $query
     *  @return Builder<Room> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
