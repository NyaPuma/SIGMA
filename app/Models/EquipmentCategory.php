<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EquipmentCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class EquipmentCategory extends Model
{
    /** @use HasFactory<EquipmentCategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'equipment_categories';

    protected $fillable = [
        'name',
        'active',
    ];

    /**
     * Attribute type casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Category has many equipments.
     */
    /** @return HasMany<Equipment, $this> */
    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class, 'category_id');
    }

    /**
     * Scope to filter active categories.
     */
    /** @param Builder<EquipmentCategory> $query
     *  @return Builder<EquipmentCategory> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
