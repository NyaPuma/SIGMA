<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Part;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PartService
{
    /**
     * Paginated parts listing with search and filters.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\Part>
     *
     * @param  string|null  $search  search by sku, name, brand or reference
     * @param  string|null  $status  'low' | 'out' | 'all' | '' | null
     * @param  int|null  $categoryId  filter by category
     */
    public function listPaginated(
        ?string $search = null,
        ?string $status = null,
        ?int $categoryId = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = Part::query()->with(['category', 'taxRate']);

        if ($search !== null && $search !== '') {
            $safeSearch = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($sub) use ($safeSearch): void {
                $sub->where('name', 'like', "%{$safeSearch}%")
                    ->orWhere('sku', 'like', "%{$safeSearch}%")
                    ->orWhere('brand', 'like', "%{$safeSearch}%")
                    ->orWhere('manufacturer_ref', 'like', "%{$safeSearch}%");
            });
        }

        if ($status === 'low') {
            $query->lowStock();
        } elseif ($status === 'out') {
            $query->outOfStock();
        }

        if ($categoryId !== null && $categoryId > 0) {
            $query->where('part_category_id', $categoryId);
        }

        return $query->orderBy('name')->paginate($perPage);
    }
}
