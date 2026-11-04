<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketStatusChanged;
use App\Models\TicketStatus;
use App\Models\TicketWorkflowHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class LogTicketWorkflowChange implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The maximum number of times the listener may be attempted on the queue.
     */
    public int $tries = 3;

    public function handle(TicketStatusChanged $event): void
    {
        // One query for both rows: the transition always has an origin and a destination,
        // and reading them separately doubled the lookups on every status change.
        $statuses = TicketStatus::whereIn('name', [$event->oldStatus->value, $event->newStatus->value])
            ->get(['id', 'name'])
            ->keyBy('name');

        // `changedBy` is captured by the observer while the request still has a user; the
        // assignment is the last known actor once the job runs on the queue, where this
        // listener has no authenticated user at all.
        $technicianId = $event->changedBy?->id;
        $technicianId ??= auth()->id() ?? $event->ticket->assigned_to;

        TicketWorkflowHistory::create([
            'ticket_id' => $event->ticket->id,
            'origin_status_id' => $statuses->get($event->oldStatus->value)?->id,
            'destination_status_id' => $statuses->get($event->newStatus->value)?->id,
            'technician_id' => $technicianId,
            'comment' => "Status changed from \"{$event->oldStatus->value}\" to \"{$event->newStatus->value}\".",
        ]);
    }

    /**
     * Logs the failure when the workflow history write fails.
     */
    public function failed(TicketStatusChanged $event, Throwable $exception): void
    {
        Log::error('Failed to log ticket workflow change to history', [
            'ticket_id' => $event->ticket->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
