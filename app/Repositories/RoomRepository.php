<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Room;
use App\Repositories\Contracts\RoomRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class RoomRepository implements RoomRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Room
    {
        return Room::find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getAll(array $relations = [], array $withCounts = []): LengthAwarePaginator
    {
        return Room::with($relations)->withCount($withCounts)->latest()->paginate(15);
    }

    /**
     * {@inheritDoc}
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActive(): array
    {
        return Room::where('active', true)
            ->orderBy('location')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'location'])
            ->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Room
    {
        return Room::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(Room $room, array $data): bool
    {
        return $room->update($data);
    }

    /**
     * {@inheritDoc}
     */
    public function inactivate(Room $room): bool
    {
        return $room->update(['active' => false]);
    }
}
