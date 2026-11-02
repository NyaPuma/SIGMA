<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $purchase_date
 * @property Carbon|null $warranty_until
 * @property-read EquipmentCategory|null $category
 * @property-read Room|null $room
 */
final class Equipment extends Model
{
    use Auditable;

    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Explicit table name mapping.
     */
    protected $table = 'equipments';

    protected $fillable = [
        'name',
        'serial',
        'asset_tag',
        'brand',
        'model',
        'manufacturer',
        'purchase_date',
        'warranty_until',
        'status',
        'active',
        'notes',
        'room_id',
        'category_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'purchase_date' => 'date',
            'warranty_until' => 'date',
        ];
    }

    /**
     * Category that the equipment belongs to.
     */
    /** @return BelongsTo<EquipmentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class);
    }

    /**
     * Room where the equipment is located.
     */
    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Maintenance tickets associated with this equipment.
     */
    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Preventive maintenance plans defined for this equipment.
     */
    /** @return HasMany<MaintenancePlan, $this> */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    /**
     * Scope to filter active equipment records.
     */
    /** @param Builder<Equipment> $query
     *  @return Builder<Equipment> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
