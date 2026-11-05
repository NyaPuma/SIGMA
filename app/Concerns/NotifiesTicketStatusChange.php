<?php

namespace App\Concerns;

use App\Enums\TicketStatusEnum;
use App\Events\TicketStatusUpdated;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\Log;
use Throwable;

trait NotifiesTicketStatusChange
{
    /**
     * Dispatch the status-change event that makes the transition known to the ticket
     * owner and the assigned technician (see `EventServiceProvider`).
     *
     * Accepts a status model (callers capture `$ticket->status` before the transition)
     * or a raw status value.
     */
    protected function notifyStatusChange(
        Ticket $ticket,
        TicketStatusEnum|TicketStatus|string|null $oldStatus,
        TicketStatusEnum|TicketStatus|string|null $newStatus
    ): void {
        $oldStatusValue = $this->normalizeStatusValue($oldStatus);
        $newStatusValue = $this->normalizeStatusValue($newStatus);

        try {
            // SendTicketStatusNotification owns the mail: notifying here as well made every
            // transition deliver the same "status changed" message to the owner twice.
            event(new TicketStatusUpdated($ticket, $oldStatusValue, $newStatusValue));
        } catch (Throwable $e) {
            Log::warning('Failed to dispatch ticket status change.', [
                'ticket_id' => $ticket->id,
                'old_status' => $oldStatusValue,
                'new_status' => $newStatusValue,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeStatusValue(TicketStatusEnum|TicketStatus|string|null $status): string
    {
        return match (true) {
            $status instanceof TicketStatusEnum => $status->value,
            $status instanceof TicketStatus => $status->name,
            is_string($status) && $status !== '' => $status,
            default => TicketStatusEnum::Open->value,
        };
    }
}
