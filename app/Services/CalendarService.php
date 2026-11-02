<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Ticket\Queries\ScheduledEventsQuery;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class CalendarService
{
    /**
     * Window (in days) used to bound the unbounded calendar query.
     */
    private const WINDOW_DAYS = 365;

    /**
     * Gets formatted scheduled events for a specific user.
     * - Admin sees all schedules;
     * - Technician sees only their assigned tickets;
     * - Regular user sees only their own tickets.
     *
     * @return Collection<int, array{id: int, title: string, start: string, end: string|null, extendedProps: array{url: string, scheduled: bool, equipment: string|null, technician: string|null, description: string}, editable: bool}>
     */
    public function getScheduledEventsForUser(User $user): Collection
    {
        $baseQuery = Ticket::with(['equipment', 'equipment.category', 'technician'])
            ->whereNull('deleted_at');

        if ($user->isTechnician()) {
            $baseQuery->where('assigned_to', $user->id);
        } elseif (! $user->isAdmin()) {
            $baseQuery->where('user_id', $user->id);
        }

        $from = Carbon::now()->subDays(self::WINDOW_DAYS);
        $to = Carbon::now()->addDays(self::WINDOW_DAYS);

        $baseQuery->where(function ($q) use ($from, $to) {
            $q->where(function ($q2) use ($from, $to) {
                $q2->whereBetween('scheduled_at', [$from, $to])
                    ->orWhereBetween('opened_at', [$from, $to])
                    ->orWhereBetween('resolved_at', [$from, $to]);
            });
        });

        $tickets = $baseQuery->get();

        return $tickets->toBase()->map(function (Ticket $ticket) use ($user): array {
            $start = $ticket->scheduled_at ?? $ticket->opened_at ?? $ticket->resolved_at;

            if (! $start) {
                return [];
            }

            $isScheduled = (bool) $ticket->scheduled_at;

            return [
                'id' => $ticket->id,
                'title' => $ticket->title ?: ($ticket->equipment->name ?? 'General Fault'),
                'start' => $start->toIso8601String(),
                'end' => $isScheduled ? ($ticket->scheduled_end?->toIso8601String()) : null,
                'extendedProps' => [
                    'url' => url("/ui/tickets/{$ticket->id}"),
                    'scheduled' => $isScheduled,
                    'equipment' => $ticket->equipment?->name,
                    'technician' => $ticket->technician?->name,
                    'description' => $ticket->description,
                ],
                'editable' => $user->isAdmin() || ($user->isTechnician() && $ticket->assigned_to === $user->id),
            ];
        })->filter()->values();
    }

    /**
     * Gets global scheduled events within an optional date range.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getScheduledEvents(?string $from = null, ?string $to = null): Collection
    {
        return (new ScheduledEventsQuery($from, $to))->execute();
    }
}
