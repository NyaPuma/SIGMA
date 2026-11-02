<?php

namespace App\Concerns;

use App\Enums\TicketStatusEnum;
use App\Events\TicketStatusUpdatedBroadcast;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Support\Facades\Log;
use Throwable;

trait BroadcastsTicketStatus
{
    /**
     * Broadcast ticket status change via WebSockets and dispatch notification to ticket owner.
     *
     * Accepts a status model (callers capture `$ticket->status` before the transition)
     * or a raw status value.
     */
    protected function broadcastStatusChange(
        Ticket $ticket,
        TicketStatusEnum|TicketStatus|string|null $oldStatus,
        TicketStatusEnum|TicketStatus|string|null $newStatus
    ): void {
        $oldStatusValue = $this->normalizeStatusValue($oldStatus);
        $newStatusValue = $this->normalizeStatusValue($newStatus);

        try {
            event(new TicketStatusUpdatedBroadcast($ticket, $oldStatusValue, $newStatusValue));

            /** @var User|null $user */
            $user = $ticket->user;

            if ($user?->email) {
                $user->notify(new TicketStatusChanged($ticket, $oldStatusValue, $newStatusValue));
            }
        } catch (Throwable $e) {
            Log::warning('Failed to broadcast ticket status change.', [
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
