<?php

namespace App\Http\Controllers;

use App\Domain\Ticket\Concerns\LocksTicketRow;
use App\DTOs\BudgetSubmissionData;
use App\Enums\BudgetStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Http\Requests\RequestBudgetRequest;
use App\Http\Requests\SubmitBudgetRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TicketStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class TicketBudgetController extends Controller
{
    use LocksTicketRow;

    public function __construct(
        private readonly TicketStatusService $statusService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Submits a budget estimate for a ticket.
     */
    public function submitEstimate(SubmitBudgetRequest $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('submitBudget', $ticket);

        // 2. Domain rules: allowed status for budget submission
        $guard = $this->ensureSubmitAllowed($ticket);
        if ($guard !== null) {
            return $guard;
        }

        // 3. Persist and answer
        return $this->processSubmission($ticket, BudgetSubmissionData::fromSubmitEstimate($request->validated()), $this->authenticatedUser($request));
    }

    /**
     * Requests detailed budget authorization for a ticket.
     */
    public function requestAuthorization(RequestBudgetRequest $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Policy (only the technician assigned to the ticket)
        $this->authorize('requestBudget', $ticket);

        // 2. Domain rules: allowed status for requesting authorization
        $guard = $this->ensureSubmitAllowed($ticket);
        if ($guard !== null) {
            return $guard;
        }

        return $this->processSubmission($ticket, BudgetSubmissionData::fromDetailedRequest($request->validated()), $this->authenticatedUser($request));
    }

    /**
     * Writes the budget request under a row lock, then follows the approval threshold.
     */
    private function processSubmission(Ticket $ticket, BudgetSubmissionData $data, User $user): JsonResponse
    {
        $thresholdRaw = config('services.custom.budget.threshold', 0);
        $threshold = is_numeric($thresholdRaw) ? (float) $thresholdRaw : 0.0;

        return DB::transaction(function () use ($ticket, $data, $user, $threshold): JsonResponse {
            // The route model was read before authorization and validation ran. Another request
            // may have claimed the ticket or ruled on the previous budget in the meantime, so the
            // rules are checked again against the committed row and all writes land as one unit.
            $this->lockTicketRow($ticket);

            $guard = $this->ensureSubmitAllowed($ticket);
            if ($guard !== null) {
                return $guard;
            }

            $this->applyBudgetChanges($ticket, $data, $user);

            return $data->estimatedBudget > $threshold
                ? $this->handleAboveThreshold($ticket, $data->estimatedBudget, $threshold)
                : $this->handleBelowThreshold($ticket, $data->estimatedBudget, $threshold);
        });
    }

    /**
     * Validates domain rules that prevent budget submission.
     */
    private function ensureSubmitAllowed(Ticket $ticket): ?JsonResponse
    {
        if ($ticket->hasStatus(TicketStatusEnum::Closed)
            || $ticket->hasStatus(TicketStatusEnum::Cancelled)
            || $ticket->hasStatus(TicketStatusEnum::Rejected)) {
            return response()->json([
                'message' => __('tickets.Não é possível submeter um orçamento para um ticket encerrado.'),
            ], 422);
        }

        if ($ticket->budget_status === BudgetStatusEnum::Pending->value) {
            return response()->json([
                'message' => __('tickets.Já existe um pedido de orçamento pendente para este ticket.'),
            ], 422);
        }

        return null;
    }

    /**
     * Applies common budget and assignment changes to the ticket.
     */
    private function applyBudgetChanges(Ticket $ticket, BudgetSubmissionData $data, User $user): void
    {
        if ($data->budgetDetails !== null) {
            /** @var array<int, array<string, mixed>> $budgetDetails */
            $budgetDetails = $data->budgetDetails;
            $ticket->budget_details = $budgetDetails;
        }

        if ($user->isTechnician() && ! $ticket->assigned_to) {
            $ticket->assigned_to = $user->id;
        }

        $ticket->budget_requested = true;
        $ticket->budget_amount = $data->estimatedBudget;

        // The stored decision belongs to the request this one replaces. Leaving approver and
        // decision time behind would attribute this new request to that admin and make the
        // budget-pause KPI measure a decision taken before the request existed.
        $ticket->budget_approved_by = null;
        $ticket->budget_decided_at = null;
    }

    /**
     * Processes the flow when the budget exceeds the configured threshold.
     */
    private function handleAboveThreshold(Ticket $ticket, float $amount, float $threshold): JsonResponse
    {
        $ticket->budget_status = BudgetStatusEnum::Pending->value;
        $ticket->budget_requested_at = now();

        $pendingStatusId = $this->statusService->getByName(TicketStatusEnum::PendingBudget);
        if ($pendingStatusId) {
            $ticket->status_id = $pendingStatusId;
        }

        $ticket->save();
        $ticket->loadMissing(['equipment', 'room', 'technician', 'status']);

        $this->notificationService->notifyBudgetSubmitted(
            $ticket,
            __('tickets.O técnico submeteu um orçamento de :amount€ para o ticket #:id - :title. Aguarda aprovação.', [
                'amount' => $amount,
                'id' => $ticket->id,
                'title' => $ticket->title,
            ])
        );

        return response()->json([
            'message' => __('tickets.Custo estimado excede o limiar. Ticket pendente de aprovação orçamental.'),
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Processes the flow when the budget is below the configured threshold.
     */
    private function handleBelowThreshold(Ticket $ticket, float $amount, float $threshold): JsonResponse
    {
        $ticket->budget_status = null;

        $inProgressId = $this->statusService->getByName(TicketStatusEnum::InProgress);
        if ($inProgressId) {
            $ticket->status_id = $inProgressId;
        }

        $ticket->save();
        $ticket->loadMissing(['equipment', 'room', 'technician', 'status']);

        $this->notificationService->notifyBudgetAutoApproved(
            $ticket,
            __('tickets.Orçamento de :amount€ para o ticket #:id foi auto-aprovado (dentro do limiar de :threshold€). Pode prosseguir.', [
                'amount' => $amount,
                'id' => $ticket->id,
                'threshold' => $threshold,
            ])
        );

        return response()->json([
            'message' => __('common.Custo estimado dentro da autonomia. Pode prosseguir com a intervenção.'),
            'ticket' => new TicketResource($ticket),
        ]);
    }
}
