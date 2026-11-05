<?php

namespace App\Events;

use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketStatusChanged
{
    use Dispatchable, SerializesModels;

    public TicketStatusEnum $oldStatus;

    public TicketStatusEnum $newStatus;

    public CarbonImmutable $changedAt;

    public function __construct(
        public Ticket $ticket,
        TicketStatusEnum|string $oldStatus,
        TicketStatusEnum|string $newStatus,
        public ?User $changedBy = null,
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
