<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Equipment;
use Illuminate\Pagination\LengthAwarePaginator;

interface EquipmentRepositoryInterface
{
    /**
     * Find an equipment by its ID.
     */
    public function findById(int $id): ?Equipment;

    /**
     * Return a paginated list of equipment.
     *
     * @param  array<int, string>  $relations
     * @return LengthAwarePaginator<int, Equipment>
     */
    public function getAll(array $relations = [], ?string $search = null, ?string $status = null, ?string $category = null): LengthAwarePaginator;

    /**
     * Create a new equipment record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Equipment;

    /**
     * Update an existing equipment.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Equipment $equipment, array $data): bool;

    /**
     * Delete an equipment from the database.
     */
    public function delete(Equipment $equipment): bool;
}
