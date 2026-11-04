<?php

namespace App\Actions;

use App\Domain\Ticket\Concerns\LocksTicketRow;
use App\DTOs\BudgetSubmissionData;
use App\Enums\BudgetStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SubmitBudgetAction
{
    use LocksTicketRow;

    public function execute(Ticket $ticket, BudgetSubmissionData $data): Ticket
    {
        if ($data->estimatedBudget <= 0) {
            throw new InvalidArgumentException('The budget amount must be greater than 0.');
        }

        return DB::transaction(function () use ($ticket, $data) {
            $this->lockTicketRow($ticket);

            if ($ticket->hasStatus(TicketStatusEnum::Closed)) {
                throw new InvalidArgumentException('Cannot submit a budget for a ticket that is already closed.');
            }

            if ($ticket->budget_status === BudgetStatusEnum::Pending->value) {
                throw new InvalidArgumentException('A pending budget request already exists for this ticket.');
            }

            $ticket->update([
                'budget_requested' => true,
                'budget_status' => BudgetStatusEnum::Pending->value,
                'budget_amount' => $data->estimatedBudget,
                // Ticket::$casts stores budget_details as an array, so Eloquent encodes it on
                // save. json_encode() here would write a doubly encoded blob that reads back as
                // a string, unlike the array the budget controller persists.
                'budget_details' => $data->budgetDetails,
                'budget_requested_at' => now(),
                'budget_feedback' => null,
                // The previous decision describes a request that no longer exists. Leaving the
                // approver and the decision time set would attribute this pending request to
                // them, and the pause KPI would measure a decision made before this request.
                'budget_approved_by' => null,
                'budget_decided_at' => null,
            ]);

            return $ticket->load(['technician', 'status', 'user']);
        });
    }
}
