<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'nif',
        'contact',
        'email',
        'address',
        'avg_lead_time_days',
    ];

    protected function casts(): array
    {
        return [
            'avg_lead_time_days' => 'integer',
        ];
    }

    /** @return BelongsToMany<Part, $this, Pivot> */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'part_supplier')
            ->withPivot(['price', 'supplier_ref', 'lead_time_days'])
            ->withTimestamps();
    }
}
