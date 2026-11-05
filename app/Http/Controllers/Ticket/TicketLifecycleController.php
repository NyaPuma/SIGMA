<?php

namespace App\Http\Controllers\Ticket;

use App\Concerns\NotifiesTicketStatusChange;
use App\Enums\TicketStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TicketLifecycleController extends Controller
{
    use NotifiesTicketStatusChange;

    public function __construct(
        private readonly TicketWorkflowService $workflowService,
    ) {}

    /**
     * Reopens a previously closed or cancelled ticket.
     */
    public function reopen(Request $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('reopen', $ticket);

        $oldStatus = $ticket->status;

        // 2. Execute the reopen in the workflow service
        if (! $this->workflowService->reopen($ticket)) {
            return response()->json([
                'message' => __('tickets.Apenas tickets fechados ou cancelados podem ser reabertos.'),
            ], 422);
        }

        // Invalidate the cached relation to reflect the new state
        $ticket->unsetRelation('status');

        // 3. Notify the owner and the technician about the status change
        $this->notifyStatusChange($ticket, $oldStatus, $ticket->status);

        $ticket->loadMissing(['equipment', 'room', 'technician', 'status']);

        return response()->json([
            'message' => __('messages.Ticket reaberto com sucesso.'),
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Cancels a ticket submitted by the user.
     */
    public function cancel(Request $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Policy (Validates ownership and permissions)
        $this->authorize('cancel', $ticket);

        // 2. Eligible state validation for cancellation
        if (! $ticket->hasStatus(TicketStatusEnum::Open)) {
            return response()->json([
                'message' => __('tickets.Apenas tickets no estado "Aberto" podem ser cancelados.'),
            ], 422);
        }

        $oldStatus = $ticket->status;

        // 3. Execute the cancellation in the service
        $this->workflowService->cancel($ticket);

        // Invalidate the cached relation to reflect the new state
        $ticket->unsetRelation('status');

        // 4. Notify the owner and the technician about the status change
        $this->notifyStatusChange($ticket, $oldStatus, TicketStatusEnum::Cancelled);

        $ticket->loadMissing(['equipment', 'room', 'technician', 'status']);

        return response()->json([
            'message' => __('messages.Ticket cancelado com sucesso.'),
            'ticket' => new TicketResource($ticket),
        ]);
    }
}
