<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketStatusUpdated;
use App\Models\User;
use App\Notifications\NewTicketNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotifyAssignedTechnician implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The maximum number of times the listener may be attempted on the queue.
     */
    public int $tries = 3;

    /**
     * Wait time (in seconds) between attempts.
     *
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30];

    public function handle(TicketStatusUpdated $event): void
    {
        $ticket = $event->ticket;

        if (! $ticket->assigned_to) {
            return;
        }

        /** @var User|null $technician */
        $technician = $ticket->technician;

        if ($technician instanceof User && $technician->email) {
            $technician->notify(new NewTicketNotification($ticket));
        }
    }

    /**
     * Logs a warning when notification delivery fails after all attempts.
     */
    public function failed(TicketStatusUpdated $event, Throwable $exception): void
    {
        Log::warning('Failed to notify assigned technician', [
            'ticket_id' => $event->ticket->id,
            'assigned_to' => $event->ticket->assigned_to,
            'error' => $exception->getMessage(),
        ]);
    }
}
