<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketCreated
{
    use Dispatchable, SerializesModels;

    public CarbonImmutable $createdAt;

    public function __construct(
        public Ticket $ticket,
        public User $creator,
        ?CarbonImmutable $createdAt = null,
    ) {
        $this->createdAt = $createdAt ?? CarbonImmutable::now();
    }
}
