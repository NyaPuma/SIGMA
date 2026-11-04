<?php

namespace App\Domain\Ticket\Actions;

use App\Domain\Ticket\Concerns\LocksTicketRow;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\DB;

final readonly class CancelTicketAction
{
    use LocksTicketRow;

    public function __construct(
        private TicketStatusService $statusService,
    ) {}

    /**
     * Cancels a support ticket.
     */
    public function execute(Ticket $ticket): bool
    {
        $cancelledStatusId = $this->statusService->getByName(TicketStatusEnum::Cancelled);

        return DB::transaction(function () use ($ticket, $cancelledStatusId): bool {
            $this->lockTicketRow($ticket);

            if ($ticket->status_id === $cancelledStatusId) {
                return true;
            }

            $ticket->status_id = $cancelledStatusId;
            $ticket->closed_at = now();

            $saved = $ticket->save();

            return $saved;
        });
    }
}
