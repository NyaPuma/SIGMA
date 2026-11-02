<?php

namespace App\Http\Controllers\Ticket;

use App\Concerns\BroadcastsTicketStatus;
use App\Enums\TicketStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTechnicianToTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TechnicianAssignmentService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\JsonResponse;

final class TicketAssignmentController extends Controller
{
    use BroadcastsTicketStatus;

    public function __construct(
        private readonly TechnicianAssignmentService $technicianService,
        private readonly TicketWorkflowService $workflowService,
    ) {}

    /**
     * Assigns a technician to a ticket and updates the status to In Progress.
     */
    public function __invoke(AssignTechnicianToTicketRequest $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Laravel's native Policy
        $this->authorize('assign', $ticket);

        // 2. Guard: closed tickets cannot receive a technician
        if ($ticket->hasStatus(TicketStatusEnum::Closed) || $ticket->hasStatus(TicketStatusEnum::Cancelled)) {
            return response()->json([
                'message' => __('tickets.Não é possível atribuir um técnico a um ticket encerrado.'),
            ], 422);
        }

        // 3. Preserve the previous state directly as an Enum
        $oldStatus = $ticket->status;

        // 4. Execute the assignment in the domain service (supports null = automatic assignment)
        $technicianIdRaw = $request->validated()['technician_id'] ?? null;
        $technicianId = is_numeric($technicianIdRaw) ? (int) $technicianIdRaw : null;
        $technician = $this->technicianService->assignToTicket($ticket, $technicianId);

        // 5. Handle assignment failure (invalid/unavailable technician)
        if ($technician === null) {
            $message = $technicianId
                ? __('validation.Técnico selecionado é inválido ou indisponível.')
                : __('common.Não existem técnicos disponíveis de momento.');

            return response()->json(['message' => $message], 422);
        }

        // 6. Transition the ticket to In Progress (consistent with the broadcast)
        $this->workflowService->startRepair($ticket);

        // Prevent the status relation (already loaded) from becoming stale in the response
        $ticket->unsetRelation('status');

        // 7. Emit status change to WebSocket channels
        $this->broadcastStatusChange($ticket, $oldStatus, TicketStatusEnum::InProgress);

        // 8. Load relations needed for the Resource
        $ticket->loadMissing(['equipment', 'room', 'technician', 'status']);

        return response()->json([
            'message' => __('messages.Técnico atribuído com sucesso.'),
            'ticket' => new TicketResource($ticket),
        ]);
    }
}
