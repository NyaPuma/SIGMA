<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\ScheduleMaintenanceData;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketStatusService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a preventive maintenance ticket already scheduled on the calendar.
 */
final readonly class ScheduleMaintenanceAction
{
    public function __construct(
        private TicketStatusService $statusService,
    ) {}

    public function execute(User $creator, ScheduleMaintenanceData $data): Ticket
    {
        $openStatusId = $this->statusService->getByName(TicketStatusEnum::Open);

        $scheduledAt = Carbon::parse($data->scheduledAt);

        return DB::transaction(function () use ($creator, $data, $openStatusId, $scheduledAt): Ticket {
            $ticket = Ticket::create([
                // Only 30 characters fit tickets.reference, and uniqid() is 13 long: the full
                // value overflowed the column on MySQL/PostgreSQL (SQLite silently ignores the
                // limit). The microsecond tail is unique inside its second, so it stays collision-free.
                'reference' => 'MNT-'.now()->format('YmdHis').'-'.strtoupper(substr(uniqid(), -5)),
                'title' => trim($data->title),
                'description' => trim($data->description ?? __('maintenance_plan.Manutenção preventiva agendada.')),
                'priority' => 'média',
                'user_id' => $creator->id,
                'equipment_id' => $data->equipmentId,
                'assigned_to' => $data->assignedTo,
                'status_id' => $openStatusId,
                'opened_at' => now(),
                'scheduled_at' => $scheduledAt,
                'scheduled_end' => $scheduledAt->copy()->addHours(2),
                'scheduled' => true,
            ]);

            return $ticket->load(['user', 'equipment', 'room', 'technician', 'status']);
        });
    }
}
