<?php

namespace App\Http\Controllers\Ticket;

use App\Concerns\NotifiesTicketStatusChange;
use App\Enums\TicketStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseTicketRequest;
use App\Http\Requests\CloseTicketSimpleRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\LocalizationService;
use App\Services\NotificationService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\JsonResponse;

final class TicketCloseController extends Controller
{
    use NotifiesTicketStatusChange;

    public function __construct(
        private readonly TicketWorkflowService $workflowService,
        private readonly NotificationService $notificationService,
        private readonly LocalizationService $localization,
    ) {}

    /**
     * Simple/quick close of an in-progress ticket.
     */
    public function simpleClose(CloseTicketSimpleRequest $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('close', $ticket);

        // 2. Initial state validation
        if (! $ticket->hasStatus(TicketStatusEnum::InProgress)) {
            return response()->json([
                'message' => __('tickets.Apenas tickets com o estado "Em Curso" podem ser fechados rapidamente.'),
            ], 422);
        }

        $oldStatus = $ticket->status;

        $reportRaw = $request->input('technical_report');

        // 3. Execute the closing workflow
        $this->workflowService->close(
            ticket: $ticket,
            cost: $request->float('cost'),
            report: is_string($reportRaw) ? $reportRaw : null,
            minutesSpent: $request->integer('minutes_spent')
        );

        // 4. Notify the owner and the technician about the status change
        $this->notifyStatusChange($ticket, $oldStatus, TicketStatusEnum::Closed);

        $ticket->loadMissing(['equipment', 'room', 'technician', 'status']);

        return response()->json([
            'message' => __('messages.Ticket fechado com sucesso.'),
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Final close with pending priority verification and notification dispatch.
     */
    public function closeFinal(CloseTicketRequest $request, Ticket $ticket): JsonResponse
    {
        // 1. Authorization via Policy
        $this->authorize('close', $ticket);

        $user = $this->authenticatedUser($request);
        $force = $request->boolean('force');
        $oldStatus = $ticket->status;

        // 2. Validate pending tickets with higher priority
        if (! $force) {
            $higherPriority = $this->workflowService->findHigherPriorityTickets($ticket);
            if ($higherPriority['has_higher']) {
                return response()->json([
                    'warning' => true,
                    'message' => __('tickets.Existem :total ticket(s) de prioridade mais alta por atender.', [
                        'total' => $higherPriority['total'],
                    ]),
                    'urgent_tickets_count' => $higherPriority['total'],
                    'current_priority' => $ticket->priority,
                    'can_force' => true,
                ], 409);
            }
        }

        $cost = $request->float('actual_cost');
        // Keep null when absent to avoid overwriting the existing technical report
        $reportRaw = $request->input('report');
        $report = is_string($reportRaw) ? $reportRaw : null;

        // 3. Execute the closing in the workflow service
        $this->workflowService->close(
            ticket: $ticket,
            cost: $cost,
            report: $report,
            minutesSpent: $request->integer('minutes_spent')
        );

        // 4. Notify if priority was overridden
        if ($force) {
            $higherPriority = $this->workflowService->findHigherPriorityTickets($ticket);
            if ($higherPriority['has_higher']) {
                $this->notificationService->notifyPriorityOverride($ticket, $user, $higherPriority['total']);
            }
        }

        // 5. Notify the owner and the technician about the status change
        $this->notifyStatusChange($ticket, $oldStatus, TicketStatusEnum::Closed);

        // 6. Dispatch global closing notification
        $formattedCost = $this->localization->formatDecimal($cost);
        $this->notificationService->notifyTicketClosed(
            $ticket,
            __('tickets.O ticket #:id - :title foi concluído e fechado com custo final de :cost €.', [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'cost' => $formattedCost,
            ])
        );

        $ticket->loadMissing(['equipment', 'room', 'technician', 'status']);

        return response()->json([
            'message' => __('messages.Intervenção concluída e ticket fechado com sucesso.'),
            'ticket' => new TicketResource($ticket),
        ]);
    }
}
