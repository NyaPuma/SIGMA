<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\TicketStatusEnum;
use App\Events\TicketCreated;
use App\Events\TicketStatusChanged;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\Cache;

final readonly class TicketObserver
{
    /**
     * Handle the Ticket "saving" event.
     */
    public function saving(Ticket $ticket): void
    {
        // assigned_at is the other half of the waiting-time measurement (TicketKpiQuery
        // averages opened_at to assigned_at) and no path used to write it, so live tickets
        // waited zero minutes however long they actually sat in the queue. Every assignment
        // ends in this save, which is why the stamp lives here instead of in each caller.
        // The first assignment wins: reassigning does not move it, and a value already on the
        // row (seeded history) is left alone.
        if ($ticket->assigned_to !== null && $ticket->assigned_at === null) {
            $ticket->assigned_at = now();
        }
    }

    /**
     * Handle the Ticket "created" event.
     */
    public function created(Ticket $ticket): void
    {
        if ($ticket->user) {
            event(new TicketCreated($ticket, $ticket->user));
        }

        $this->invalidateAnalyticsCache();
    }

    /**
     * Handle the Ticket "updated" event.
     */
    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('status_id')) {
            $originalStatusId = $ticket->getOriginal('status_id');
            $newStatusId = $ticket->status_id;

            if ($originalStatusId !== null) {
                $oldStatusName = TicketStatus::where('id', $originalStatusId)->value('name');
                $newStatusName = TicketStatus::where('id', $newStatusId)->value('name');

                $actor = auth()->user();

                // The actor has to be captured here: the history listener is queued and,
                // once it runs, there is no authenticated user left to attribute the
                // transition to.
                event(new TicketStatusChanged(
                    $ticket,
                    is_string($oldStatusName) ? $oldStatusName : TicketStatusEnum::Open->value,
                    is_string($newStatusName) ? $newStatusName : TicketStatusEnum::Open->value,
                    $actor,
                ));
            }
        }

        $this->invalidateAnalyticsCache();
    }

    /**
     * Handle the Ticket "deleted" event.
     */
    public function deleted(Ticket $ticket): void
    {
        $this->invalidateAnalyticsCache();
    }

    /**
     * Handle the Ticket "restored" event.
     */
    public function restored(Ticket $ticket): void
    {
        $this->invalidateAnalyticsCache();
    }

    /**
     * Invalidates analytics and dashboard caches associated with tickets.
     */
    private function invalidateAnalyticsCache(): void
    {
        // Only the acting locale is dropped: the payload has a 60 s TTL, so the other locales
        // self-heal, whereas forgetting all 52 keys on every ticket save would cost 52 queries
        // per write (CACHE_STORE=database) for a staleness window of at most one minute.
        Cache::forget('analytics_dashboard_payload:'.app()->getLocale());
    }
}
