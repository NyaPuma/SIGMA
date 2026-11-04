<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Equipment;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Support\SqlPattern;
use Illuminate\Pagination\LengthAwarePaginator;

final class EquipmentRepository implements EquipmentRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Equipment
    {
        return Equipment::find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getAll(array $relations = [], ?string $search = null, ?string $status = null, ?string $category = null): LengthAwarePaginator
    {
        $query = Equipment::with($relations)->latest();

        // Search filter (name, serial, brand, model)
        if ($search) {
            SqlPattern::whereContains($query, ['name', 'serial', 'brand', 'model'], $search);
        }

        // Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // Category filter
        if ($category) {
            $query->whereHas('category', fn ($q) => $q->where('name', $category));
        }

        return $query->paginate(15);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Equipment
    {
        return Equipment::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(Equipment $equipment, array $data): bool
    {
        return $equipment->update($data);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Equipment $equipment): bool
    {
        return (bool) $equipment->delete();
    }
}
