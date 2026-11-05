<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketStatusUpdated;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendTicketStatusNotification implements ShouldQueue
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

        /** @var User|null $user */
        $user = $ticket->user;

        if ($user instanceof User && $user->email) {
            $user->notify(new TicketStatusChanged(
                $ticket,
                $event->oldStatus->value,
                $event->newStatus->value
            ));
        }
    }

    /**
     * Logs a warning when notification delivery fails after all attempts.
     */
    public function failed(TicketStatusUpdated $event, Throwable $exception): void
    {
        Log::warning('Failed to send ticket status notification', [
            'ticket_id' => $event->ticket->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
