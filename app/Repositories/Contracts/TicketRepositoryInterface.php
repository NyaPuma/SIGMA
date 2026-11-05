<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Ticket;
use Illuminate\Pagination\LengthAwarePaginator;

interface TicketRepositoryInterface
{
    /**
     * Find a ticket by its ID.
     */
    public function findById(int $id): ?Ticket;

    /**
     * Return a paginated list of tickets.
     *
     * @param  array<int, string>  $relations
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function getAll(array $relations = []): LengthAwarePaginator;

    /**
     * Create a new ticket.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Ticket;

    /**
     * Update an existing ticket.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data): bool;

    /**
     * Delete a ticket from the database.
     */
    public function delete(Ticket $ticket): bool;

    /**
     * Return all open tickets in a paginated manner.
     *
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function getOpenTickets(): LengthAwarePaginator;

    /**
     * Return tickets assigned to a specific technician.
     *
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function getTicketsByTechnician(int $technicianId): LengthAwarePaginator;

    /**
     * Return tickets created by a specific user.
     *
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function getTicketsByUser(int $userId): LengthAwarePaginator;
}
