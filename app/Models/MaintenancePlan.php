<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MaintenancePlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read Equipment|null $equipment
 * @property-read Collection<int, Part> $parts
 */
final class MaintenancePlan extends Model
{
    /** @use HasFactory<MaintenancePlanFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'equipment_id',
        'name',
        'interval_type',
        'interval_value',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'interval_value' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return BelongsToMany<Part, $this, Pivot> */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'maintenance_plan_part')
            ->withPivot(['expected_quantity'])
            ->withTimestamps();
    }

    /** @param Builder<MaintenancePlan> $query
     *  @return Builder<MaintenancePlan> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
