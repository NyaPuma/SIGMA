<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Ticket\Actions\CancelTicketAction;
use App\Domain\Ticket\Actions\CheckHigherPriorityAction;
use App\Domain\Ticket\Actions\CloseTicketAction;
use App\Domain\Ticket\Actions\ReopenTicketAction;
use App\Domain\Ticket\Actions\StartTicketAction;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TicketWorkflowService
{
    public function __construct(
        private readonly TicketStatusService $statusService,
        private readonly StartTicketAction $startAction,
        private readonly CloseTicketAction $closeAction,
        private readonly ReopenTicketAction $reopenAction,
        private readonly CancelTicketAction $cancelAction,
        private readonly CheckHigherPriorityAction $checkHigherPriorityAction,
    ) {}

    /**
     * Starts ticket repair.
     */
    public function startRepair(Ticket $ticket, ?User $user = null): bool
    {
        return $this->startAction->execute($ticket, $user);
    }

    /**
     * Reopens a previously closed or cancelled ticket.
     */
    public function reopen(Ticket $ticket): bool
    {
        return $this->reopenAction->execute($ticket);
    }

    /**
     * Cancels a ticket.
     */
    public function cancel(Ticket $ticket): bool
    {
        return $this->cancelAction->execute($ticket);
    }

    /**
     * Closes a ticket with cost, report, and time spent data within a transaction.
     */
    public function close(Ticket $ticket, ?float $cost = null, ?string $report = null, ?int $minutesSpent = null): bool
    {
        return DB::transaction(fn (): bool => $this->closeAction->execute($ticket, $cost, $report, $minutesSpent));
    }

    /**
     * Checks if the ticket can be auto-closed based on the configured cost threshold.
     */
    public function checkAutoClose(Ticket $ticket, float $threshold): bool
    {
        if ($ticket->estimated_cost === null || $ticket->estimated_cost > $threshold) {
            return false;
        }

        $statusId = $this->statusService->getByName(TicketStatusEnum::Closed);

        $ticket->status_id = $statusId;

        $ticket->closed_at = now();

        // Same completion stamp CloseTicketAction writes, so any ticket that reaches Closed
        // carries resolved_at and the calendar ranges stay meaningful.
        $ticket->resolved_at = $ticket->resolved_at ?? now();

        return $ticket->save();
    }

    /**
     * Finds higher-priority tickets in the current context.
     *
     * @return array{total: int, assigned_to_user: int, has_higher: bool}
     */
    public function findHigherPriorityTickets(Ticket $ticket): array
    {
        return $this->checkHigherPriorityAction->execute($ticket);
    }
}
