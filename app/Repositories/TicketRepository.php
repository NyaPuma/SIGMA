<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class TicketRepository implements TicketRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Ticket
    {
        return Ticket::find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findWithRelations(int $id, array $relations = []): ?Ticket
    {
        return Ticket::with($relations)->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function getAll(array $relations = []): LengthAwarePaginator
    {
        $perPage = config('services.custom.pagination.default_per_page', 15);

        return Ticket::with($relations)->latest()->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Ticket
    {
        return Ticket::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(Ticket $ticket, array $data): bool
    {
        return $ticket->update($data);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Ticket $ticket): bool
    {
        return (bool) $ticket->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function getOpenTickets(): LengthAwarePaginator
    {
        $perPage = config('services.custom.pagination.default_per_page', 15);

        return Ticket::with(['equipment', 'room', 'user', 'status', 'technician'])
            ->open()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function getTicketsByTechnician(int $technicianId): LengthAwarePaginator
    {
        $perPage = config('services.custom.pagination.default_per_page', 15);

        return Ticket::with(['equipment', 'room', 'user', 'status', 'technician'])
            ->forTechnician($technicianId)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function getTicketsByUser(int $userId): LengthAwarePaginator
    {
        $perPage = config('services.custom.pagination.default_per_page', 15);

        return Ticket::with(['equipment', 'room', 'technician', 'status'])
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
    }
}
