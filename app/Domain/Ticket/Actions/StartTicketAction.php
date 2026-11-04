<?php

namespace App\Domain\Ticket\Actions;

use App\Domain\Ticket\Concerns\LocksTicketRow;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\DB;

final readonly class StartTicketAction
{
    use LocksTicketRow;

    public function __construct(
        private TicketStatusService $statusService,
    ) {}

    public function execute(Ticket $ticket, ?User $user = null): bool
    {
        $inProgressStatusId = $this->statusService->getByName(TicketStatusEnum::InProgress);

        return DB::transaction(function () use ($ticket, $inProgressStatusId, $user) {
            $this->lockTicketRow($ticket);

            if ($ticket->status_id === $inProgressStatusId) {
                return true;
            }

            $ticket->status_id = $inProgressStatusId;
            $ticket->assigned_to = $ticket->assigned_to ?? $user?->id;

            $ticket->in_progress_at = $ticket->in_progress_at ?? now();

            $saved = $ticket->save();

            return $saved;
        });
    }
}
