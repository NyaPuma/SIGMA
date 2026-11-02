<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TicketFilters;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class TicketSearchService
{
    public function __construct(
        private readonly TicketStatusService $statusService,
    ) {}

    /**
     * Executes ticket search and filtering with pagination.
     *
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function search(TicketFilters $filters): LengthAwarePaginator
    {
        $query = Ticket::with(['equipment', 'room', 'user', 'status', 'technician']);

        if ($filters->query !== null && $filters->query !== '') {
            $q = str_replace(['%', '_'], ['\%', '\_'], $filters->query);
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($filters->priority !== null) {
            $query->where('priority', $filters->priority->value);
        }

        if ($filters->status !== null) {
            $statusEnum = TicketStatusEnum::normalize($filters->status);
            if ($statusEnum) {
                $statusId = $this->statusService->getByName($statusEnum);
                $query->where('status_id', $statusId);
            }
        }

        if ($filters->userId !== null) {
            $query->where('user_id', $filters->userId);
        }

        if ($filters->technicianId !== null) {
            $query->where('assigned_to', $filters->technicianId);
        }

        if ($filters->equipmentId !== null) {
            $query->where('equipment_id', $filters->equipmentId);
        }

        if ($filters->roomId !== null) {
            $query->where('room_id', $filters->roomId);
        }

        if ($filters->dateFrom !== null && $filters->dateTo !== null && $filters->dateFrom > $filters->dateTo) {
            throw new \InvalidArgumentException('Start date cannot be after end date.');
        }

        $this->applyDateFilters(
            $query,
            $filters->dateFrom?->toDateString(),
            $filters->dateTo?->toDateString(),
        );

        /** @var int $perPage */
        $perPage = config('services.custom.pagination.default_per_page', 15);

        return $query->latest()->paginate($perPage);
    }

    /**
     * Applies date filters to the ticket query safely.
     *
     * @param  Builder<Ticket>  $query
     */
    private function applyDateFilters(Builder $query, ?string $dateFrom, ?string $dateTo): void
    {
        if ($dateFrom !== null && $dateTo !== null) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo.' 23:59:59']);
        } elseif ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        } elseif ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
    }
}
