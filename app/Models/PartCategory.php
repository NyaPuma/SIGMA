<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PartCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PartCategory extends Model
{
    /** @use HasFactory<PartCategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<Part, $this> */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'part_category_id');
    }

    /** @param Builder<PartCategory> $query
     *  @return Builder<PartCategory> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
