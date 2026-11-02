<?php

namespace App\Domain\Ticket\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class TopEntitiesQuery
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder<\App\Models\Ticket> $baseQuery
     */
    public function __construct(
        private Builder $baseQuery,
    ) {}

    /**
     * @return Collection<int, array{name: string, total: int, subtitle: 'interventions'}>
     */
    public function getTopEquipments(): Collection
    {
        return (clone $this->baseQuery)
            ->join('equipments', 'tickets.equipment_id', '=', 'equipments.id')
            ->select('equipments.id', 'equipments.name', DB::raw('COUNT(*) as total'))
            ->whereNotNull('tickets.equipment_id')
            ->groupBy('equipments.id', 'equipments.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn (mixed $row): array => [
                'name' => is_string(data_get($row, 'name')) ? (string) data_get($row, 'name') : '',
                'total' => is_numeric(data_get($row, 'total')) ? (int) data_get($row, 'total') : 0,
                'subtitle' => 'interventions',
            ]);
    }

    /**
     * @return Collection<int, array{name: string, total: int, subtitle: 'tickets'}>
     */
    public function getTopRooms(): Collection
    {
        return (clone $this->baseQuery)
            ->join('rooms', 'tickets.room_id', '=', 'rooms.id')
            ->select('rooms.id', 'rooms.name', DB::raw('COUNT(*) as total'))
            ->whereNotNull('tickets.room_id')
            ->groupBy('rooms.id', 'rooms.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn (mixed $row): array => [
                'name' => is_string(data_get($row, 'name')) ? (string) data_get($row, 'name') : '',
                'total' => is_numeric(data_get($row, 'total')) ? (int) data_get($row, 'total') : 0,
                'subtitle' => 'tickets',
            ]);
    }

    /**
     * @return Collection<int, array{name: string, total: int, subtitle: 'actions'}>
     */
    public function getTopTechnicians(): Collection
    {
        return (clone $this->baseQuery)
            ->join('users', 'tickets.assigned_to', '=', 'users.id')
            ->select('users.id', 'users.name', DB::raw('COUNT(*) as total'))
            ->whereNotNull('tickets.assigned_to')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn (mixed $row): array => [
                'name' => is_string(data_get($row, 'name')) ? (string) data_get($row, 'name') : '',
                'total' => is_numeric(data_get($row, 'total')) ? (int) data_get($row, 'total') : 0,
                'subtitle' => 'actions',
            ]);
    }
}
