<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartUnitOfMeasureEnum;
use App\Traits\Auditable;
use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * @property-read TaxRate|null $taxRate
 * @property-read PartCategory|null $category
 * @property-read Pivot $pivot
 */
final class Part extends Model
{
    use Auditable;

    /** @use HasFactory<PartFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'brand',
        'manufacturer_ref',
        'part_category_id',
        'unit_of_measure',
        'cost_price',
        'tax_rate_id',
        'sale_price',
        'current_stock',
        'min_stock',
        'max_stock',
        'location',
        'photo',
        'active',
        'technical_notes',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'current_stock' => 'integer',
            'min_stock' => 'integer',
            'max_stock' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<PartCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'part_category_id');
    }

    /** @return BelongsTo<TaxRate, $this> */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    /** @return BelongsToMany<Supplier, $this, Pivot> */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'part_supplier')
            ->withPivot(['price', 'supplier_ref', 'lead_time_days'])
            ->withTimestamps();
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return BelongsToMany<MaintenancePlan, $this, Pivot> */
    public function maintenancePlans(): BelongsToMany
    {
        return $this->belongsToMany(MaintenancePlan::class, 'maintenance_plan_part')
            ->withPivot(['expected_quantity'])
            ->withTimestamps();
    }

    /** @param Builder<Part> $query
     *  @return Builder<Part> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope to filter parts at or below minimum stock threshold.
     */
    /** @param Builder<Part> $query
     *  @return Builder<Part> */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query
            ->where('active', true)
            ->whereColumn('current_stock', '<=', 'min_stock');
    }

    /**
     * Scope to filter out-of-stock parts.
     */
    /** @param Builder<Part> $query
     *  @return Builder<Part> */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('current_stock', 0);
    }

    /**
     * Cost price including VAT calculated from assigned tax rate.
     */
    public function priceWithVat(): float
    {
        $percent = (float) $this->taxRate?->percent;

        return round((float) $this->cost_price * (1 + $percent / 100), 2);
    }

    /**
     * Total stock valuation (current_stock * cost_price).
     */
    public function stockValue(): float
    {
        return round((float) $this->current_stock * (float) $this->cost_price, 2);
    }

    public function isLowStock(): bool
    {
        return (int) $this->current_stock <= (int) $this->min_stock;
    }

    /**
     * Validate that the unit of measure matches a valid enum case.
     */
    public function validateUnitOfMeasure(string $unit): void
    {
        if (PartUnitOfMeasureEnum::tryFrom($unit) === null) {
            throw new InvalidArgumentException("Invalid unit of measure: {$unit}");
        }
    }
}
