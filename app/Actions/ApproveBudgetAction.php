<?php

namespace App\Actions;

use App\Domain\Ticket\Concerns\LocksTicketRow;
use App\DTOs\BudgetDecisionData;
use App\Enums\BudgetStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class ApproveBudgetAction
{
    use LocksTicketRow;

    public function __construct(
        private NotificationService $notificationService,
        private TicketStatusService $statusService,
    ) {}

    public function execute(Ticket $ticket, User $admin, BudgetDecisionData $data): Ticket
    {
        $isApproved = $data->isApproved();

        $targetTicketStatus = $isApproved ? TicketStatusEnum::InProgress : TicketStatusEnum::Rejected;
        $statusId = $this->statusService->getByName($targetTicketStatus);

        $ticket = DB::transaction(function () use ($ticket, $admin, $data, $isApproved, $statusId) {
            $this->lockTicketRow($ticket);

            if (! $ticket->budget_requested || $ticket->budget_status !== BudgetStatusEnum::Pending->value) {
                throw new HttpException(422, 'No pending budget request found for this ticket.');
            }

            $ticket->budget_approved_by = $admin->id;
            $ticket->budget_decided_at = now();
            $ticket->status_id = $statusId;

            if ($isApproved) {
                $ticket->budget_status = BudgetStatusEnum::Approved->value;
                // Approval moves the ticket to In Progress, so it must stamp the start
                // time exactly like StartTicketAction — otherwise duration metrics and
                // the analytics export see a work-in-progress ticket with no start.
                $ticket->in_progress_at = $ticket->in_progress_at ?? now();
            } else {
                $ticket->budget_status = BudgetStatusEnum::Rejected->value;
                if (! empty($data->feedback)) {
                    $ticket->budget_feedback = $data->feedback;
                }
            }

            $ticket->save();

            return $ticket;
        });

        $this->notifyDecision($ticket, $data, $isApproved);

        return $ticket->load(['equipment', 'room', 'technician', 'status']);
    }

    private function notifyDecision(Ticket $ticket, BudgetDecisionData $data, bool $isApproved): void
    {
        $amountFormatted = number_format($ticket->budget_amount ?? 0, 2, ',', '.').'€';

        $message = $isApproved
            ? "Budget of {$amountFormatted} for ticket #{$ticket->id} was APPROVED."
            : "Budget of {$amountFormatted} for ticket #{$ticket->id} was REJECTED.";

        if (! $isApproved && ! empty($data->feedback)) {
            $message .= " Reason: {$data->feedback}";
        }

        $this->notificationService->notifyBudgetDecision($ticket, $data->decision->value, $message);
    }
}
