<?php

namespace App\Domain\Ticket\Actions;

use App\Domain\Ticket\Concerns\LocksTicketRow;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\DB;

final readonly class ReopenTicketAction
{
    use LocksTicketRow;

    public function __construct(
        private TicketStatusService $statusService,
    ) {}

    public function execute(Ticket $ticket): bool
    {
        $openStatusId = $this->statusService->getByName(TicketStatusEnum::Open);

        return DB::transaction(function () use ($ticket, $openStatusId): bool {
            // Reopen is the one transition whose guard decides whether the request is valid
            // at all, so the guard has to read the row as it stands now, not as it looked
            // when the controller loaded it.
            $this->lockTicketRow($ticket);

            if (! $ticket->hasStatus(TicketStatusEnum::Closed) && ! $ticket->hasStatus(TicketStatusEnum::Cancelled)) {
                return false;
            }

            $ticket->status_id = $openStatusId;
            $ticket->reopened_at = now();
            $ticket->closed_at = null;
            // The work is unfinished again, so the completion stamp goes with closed_at;
            // closing later records a fresh one instead of keeping the abandoned date.
            $ticket->resolved_at = null;

            $saved = $ticket->save();

            return $saved;
        });
    }
}
