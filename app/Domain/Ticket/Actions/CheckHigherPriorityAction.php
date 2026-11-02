<?php

namespace App\Domain\Ticket\Actions;

use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Services\TicketStatusService;

final readonly class CheckHigherPriorityAction
{
    public function __construct(
        private TicketStatusService $statusService,
    ) {}

    /**
     * @return array{total: int, assigned_to_user: int, has_higher: bool}
     */
    public function execute(Ticket $ticket): array
    {
        $normalized = TicketPriorityEnum::normalize($ticket->priority);
        if ($normalized === null) {
            return [
                'total' => 0,
                'assigned_to_user' => 0,
                'has_higher' => false,
            ];
        }
        $currentWeight = $normalized->weight();

        $higherPriorities = array_filter(
            TicketPriorityEnum::cases(),
            fn (TicketPriorityEnum $p) => $p->weight() > $currentWeight
        );

        if (empty($higherPriorities)) {
            return [
                'total' => 0,
                'assigned_to_user' => 0,
                'has_higher' => false,
            ];
        }

        $openStatusId = $this->statusService->getByName(TicketStatusEnum::Open);
        $priorityValues = array_map(fn (TicketPriorityEnum $p) => $p->value, $higherPriorities);

        $result = Ticket::query()
            ->where('status_id', $openStatusId)
            ->where('id', '!=', $ticket->id)
            ->whereIn('priority', $priorityValues)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN assigned_to = ? THEN 1 ELSE 0 END) as assigned_to_user
            ', [$ticket->assigned_to])
            ->first();

        $totalRaw = data_get($result, 'total', 0);
        $assignedRaw = data_get($result, 'assigned_to_user', 0);

        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;
        $assignedToUser = $ticket->assigned_to !== null && is_numeric($assignedRaw) ? (int) $assignedRaw : 0;

        return [
            'total' => $total,
            'assigned_to_user' => $assignedToUser,
            'has_higher' => $total > 0,
        ];
    }
}
