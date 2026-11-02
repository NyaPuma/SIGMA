<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Equipment;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
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
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('serial', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
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
