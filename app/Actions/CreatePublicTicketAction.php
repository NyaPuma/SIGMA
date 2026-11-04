<?php

namespace App\Actions;

use App\Enums\PublicTicketProblemTypeEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Equipment;
use App\Models\Ticket;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\DB;

final readonly class CreatePublicTicketAction
{
    public function __construct(
        private TicketStatusService $statusService,
    ) {}

    /**
     * Creates a publicly reported ticket (via QR Code) without a user account.
     */
    public function execute(
        Equipment $equipment,
        PublicTicketProblemTypeEnum $problemType,
        string $description,
        ?string $reporterName = null,
        ?string $reporterContact = null,
    ): Ticket {
        $openStatusId = $this->statusService->getByName(TicketStatusEnum::Open);

        return DB::transaction(function () use ($equipment, $problemType, $description, $reporterName, $reporterContact, $openStatusId) {
            $ticket = Ticket::create([
                'title' => trim($problemType->label().' — '.$equipment->name),
                'description' => trim($description),
                'priority' => $problemType->priority()->value,
                'user_id' => null,
                'reporter_name' => trim((string) $reporterName) !== '' ? trim((string) $reporterName) : null,
                'reporter_contact' => trim((string) $reporterContact) !== '' ? trim((string) $reporterContact) : null,
                'source' => 'qr',
                'equipment_id' => $equipment->id,
                'room_id' => $equipment->room_id,
                'status_id' => $openStatusId,
                'opened_at' => now(),
            ]);

            return $ticket->load(['equipment', 'room', 'status']);
        });
    }
}
