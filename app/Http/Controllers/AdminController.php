<?php

namespace App\Http\Controllers;

use App\Actions\ApproveBudgetAction;
use App\Actions\CreatePreventiveTicketAction;
use App\DTOs\BudgetDecisionData;
use App\Enums\BudgetStatusEnum;
use App\Http\Requests\BudgetDecisionRequest;
use App\Http\Requests\StorePreventiveRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

final class AdminController extends Controller
{
    public function __construct(
        private readonly ApproveBudgetAction $approveBudgetAction,
        private readonly CreatePreventiveTicketAction $createPreventiveAction,
    ) {}

    /**
     * Approves or rejects the budget associated with a ticket.
     */
    public function approveBudget(BudgetDecisionRequest $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('approveBudget', $ticket);

        $admin = $this->authenticatedUser($request);

        // 2. Execute the budget approval/rejection action
        $updatedTicket = $this->approveBudgetAction->execute(
            $ticket,
            $admin,
            BudgetDecisionData::fromRequest($request->validated())
        );

        // 3. Validate status and set the formatted message with i18n
        $isApproved = BudgetStatusEnum::normalize($updatedTicket->budget_status) === BudgetStatusEnum::Approved;
        $message = $isApproved
            ? __('tickets.Orçamento aprovado. Ticket desbloqueado para intervenção.')
            : __('common.Orçamento recusado. Reparação abortada.');

        $updatedTicket->loadMissing(['equipment', 'room', 'technician', 'status']);

        return response()->json([
            'message' => $message,
            'ticket' => new TicketResource($updatedTicket),
        ]);
    }

    /**
     * Creates a new preventive maintenance ticket.
     */
    public function storePreventive(StorePreventiveRequest $request): JsonResponse
    {
        // 1. Authorization via Policy (verification in Model class)
        $this->authorize('createPreventive', Ticket::class);

        $admin = $this->authenticatedUser($request);

        // 2. Execute the preventive ticket creation with validated data
        /** @var array{title: string, description?: string|null, technician_id?: int|string|null} $data */
        $data = $request->validated();
        $technicianId = $data['technician_id'] ?? null;

        $ticket = $this->createPreventiveAction->execute(
            admin: $admin,
            title: $data['title'],
            description: $data['description'] ?? null,
            technician: is_numeric($technicianId) ? (int) $technicianId : null,
            scheduledAt: $request->date('scheduled_at')
        );

        $ticket->loadMissing(['equipment', 'room', 'technician', 'status']);

        return response()->json([
            'message' => __('messages.Ticket preventivo criado com sucesso.'),
            'ticket' => new TicketResource($ticket),
        ], 201);
    }
}
