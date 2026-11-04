<?php

namespace App\Domain\Ticket\Actions;

use App\Domain\Ticket\Concerns\LocksTicketRow;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\DB;

final readonly class CloseTicketAction
{
    use LocksTicketRow;

    public function __construct(
        private TicketStatusService $statusService,
    ) {}

    public function execute(
        Ticket $ticket,
        ?float $cost = null,
        ?string $report = null,
        ?int $minutesSpent = null
    ): bool {
        $closedStatusId = $this->statusService->getByName(TicketStatusEnum::Closed);

        return DB::transaction(function () use ($ticket, $closedStatusId, $cost, $report, $minutesSpent): bool {
            // `closed_at` is only kept when the row really has one: the copy the caller
            // passed may predate a concurrent reopen that just cleared it.
            $this->lockTicketRow($ticket);

            if ($ticket->status_id === $closedStatusId) {
                return true;
            }

            $attributes = [
                'status_id' => $closedStatusId,
                'closed_at' => $ticket->closed_at ?? now(),
                // resolved_at is the completion stamp the calendar ranges filter on
                // (ScheduledEventsQuery and CalendarService); closing is when the work ended, so
                // the two belong together. A row that already has one keeps its own value.
                'resolved_at' => $ticket->resolved_at ?? now(),
            ];

            if ($cost !== null) {
                $attributes['actual_cost'] = $cost;
            }

            if ($report !== null) {
                $attributes['technical_report'] = $report;
            }

            if ($minutesSpent !== null) {
                $attributes['minutes_spent'] = $minutesSpent;
            }

            $updated = $ticket->update($attributes);

            return $updated;
        });
    }
}
