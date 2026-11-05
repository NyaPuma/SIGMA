<?php

namespace App\Events;

use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the ticket controllers after a status transition has been committed.
 *
 * The name says what it does: it triggers the notification listeners registered in
 * `EventServiceProvider`. It used to be called `TicketStatusUpdatedBroadcast` and
 * implemented `ShouldBroadcastNow`, but the application has no WebSocket channel
 * (`routes/channels.php` does not exist) and no client subscribes to Echo, so the
 * broadcasting half never delivered anything.
 */
final class TicketStatusUpdated
{
    use Dispatchable, SerializesModels;

    public TicketStatusEnum $oldStatus;

    public TicketStatusEnum $newStatus;

    public CarbonImmutable $changedAt;

    public function __construct(
        public Ticket $ticket,
        TicketStatusEnum|string $oldStatus,
        TicketStatusEnum|string $newStatus,
        ?CarbonImmutable $changedAt = null,
    ) {
        $this->oldStatus = $oldStatus instanceof TicketStatusEnum
            ? $oldStatus
            : (TicketStatusEnum::normalize($oldStatus) ?? TicketStatusEnum::Open);

        $this->newStatus = $newStatus instanceof TicketStatusEnum
            ? $newStatus
            : (TicketStatusEnum::normalize($newStatus) ?? TicketStatusEnum::Open);

        $this->changedAt = $changedAt ?? CarbonImmutable::now();
    }
}
