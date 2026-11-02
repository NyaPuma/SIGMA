<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'percent',
        'is_default',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<Part, $this> */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'tax_rate_id');
    }

    /** @param Builder<TaxRate> $query
     *  @return Builder<TaxRate> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** @param Builder<TaxRate> $query
     *  @return Builder<TaxRate> */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
