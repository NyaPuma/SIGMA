<?php

namespace App\Http\Controllers;

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

final class TicketBudgetController extends Controller
{
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

        $data = BudgetSubmissionData::fromSubmitEstimate($request->validated());

        $this->applyBudgetChanges($ticket, $data, $this->authenticatedUser($request));

        $thresholdRaw = config('services.custom.budget.threshold', 0);
        $threshold = is_numeric($thresholdRaw) ? (float) $thresholdRaw : 0.0;

        if ($data->estimatedBudget > $threshold) {
            return $this->handleAboveThreshold($ticket, $data->estimatedBudget, $threshold);
        }

        return $this->handleBelowThreshold($ticket, $data->estimatedBudget, $threshold);
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

        $data = BudgetSubmissionData::fromDetailedRequest($request->validated());

        $this->applyBudgetChanges($ticket, $data, $this->authenticatedUser($request));

        $thresholdRaw = config('services.custom.budget.threshold', 0);
        $threshold = is_numeric($thresholdRaw) ? (float) $thresholdRaw : 0.0;

        if ($data->estimatedBudget > $threshold) {
            return $this->handleAboveThreshold($ticket, $data->estimatedBudget, $threshold);
        }

        return $this->handleBelowThreshold($ticket, $data->estimatedBudget, $threshold);
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

        if (! $ticket->assigned_to) {
            $ticket->assigned_to = $user->id;
        }

        $ticket->budget_requested = true;
        $ticket->budget_amount = $data->estimatedBudget;
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
