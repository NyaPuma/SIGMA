<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Equipment;
use App\Support\SqlPattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EquipmentService
{
    /**
     * Returns a paginated listing of equipment based on search terms and status.
     *
     * @return LengthAwarePaginator<int, Equipment>
     */
    public function listPaginated(?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        $query = Equipment::with('room');

        if ($search !== null && $search !== '') {
            SqlPattern::whereContains($query, ['name', 'serial', 'brand', 'model'], $search);
        }

        if ($status !== null && $status !== '') {
            // Status filter: operacional, manutenção, avariado, abatido
            $query->where('status', $status);
        }

        return $query->orderBy('name')->paginate(15);
    }
}
