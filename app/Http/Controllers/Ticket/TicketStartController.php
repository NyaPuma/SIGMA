<?php

namespace App\Http\Controllers\Ticket;

use App\Concerns\BroadcastsTicketStatus;
use App\Enums\TicketStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\NotificationService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\JsonResponse;

final class TicketStartController extends Controller
{
    use BroadcastsTicketStatus;

    public function __construct(
        private readonly TicketWorkflowService $workflowService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Starts the intervention on a ticket (changes status to In Progress).
     */
    public function __invoke(StartTicketRequest $request, Ticket $ticket): JsonResponse
    {
        // 1. Centralized authorization via Laravel Policy
        $this->authorize('start', $ticket);

        $user = $this->authenticatedUser($request);

        // 2. Eligible state validation for starting
        if (! $ticket->hasStatus(TicketStatusEnum::Open)) {
            return response()->json([
                'message' => __('tickets.Apenas tickets no estado "Aberto" podem ser iniciados.'),
            ], 422);
        }

        $oldStatus = $ticket->status;
        $force = $request->boolean('force');
        $higherPriority = $this->workflowService->findHigherPriorityTickets($ticket);

        // 3. Check for higher priority pending tickets
        if ($higherPriority['has_higher'] && ! $force) {
            $msg = __('tickets.Existem :total ticket(s) de prioridade mais alta por atender.', [
                'total' => $higherPriority['total'],
            ]);

            if ($higherPriority['assigned_to_user'] > 0) {
                $msg .= ' '.__('common.Destes, :assigned estão atribuídos a si.', [
                    'assigned' => $higherPriority['assigned_to_user'],
                ]);
            }

            $msg .= ' '.__('common.Recomenda-se resolver os mais urgentes primeiro.');

            return response()->json([
                'warning' => true,
                'message' => $msg,
                'urgent_tickets_count' => $higherPriority['total'],
                'my_urgent_tickets_count' => $higherPriority['assigned_to_user'],
                'current_priority' => $ticket->priority,
                'can_force' => true,
            ], 409);
        }

        // 4. Delegate assignment and repair start to the workflow service
        $this->workflowService->startRepair($ticket, $user);

        // 5. Notify about priority override if applicable
        if ($force && $higherPriority['has_higher']) {
            $this->notificationService->notifyPriorityOverride(
                $ticket,
                $user,
                max($higherPriority['total'], $higherPriority['assigned_to_user'])
            );
        }

        // 6. Broadcast status change in real time (WebSockets)
        $this->broadcastStatusChange($ticket, $oldStatus, TicketStatusEnum::InProgress);

        $ticket->loadMissing(['equipment', 'room', 'technician', 'status']);

        return response()->json([
            'message' => __('messages.Intervenção iniciada com sucesso.'),
            'overridden' => $force && $higherPriority['has_higher'],
            'ticket' => new TicketResource($ticket),
        ]);
    }
}
