<?php

namespace Tests\Unit\Events;

use App\Enums\TicketStatusEnum;
use App\Events\TicketStatusChanged;
use App\Models\Ticket;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\DatabaseTestCase;

class TicketStatusChangedTest extends DatabaseTestCase
{
    #[Test]
    public function it_normalizes_the_statuses_and_keeps_the_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $event = new TicketStatusChanged($ticket, 'Aberto', 'Em Curso');

        $this->assertSame($ticket->id, $event->ticket->id);
        $this->assertSame(TicketStatusEnum::Open, $event->oldStatus);
        $this->assertSame(TicketStatusEnum::InProgress, $event->newStatus);
    }

    #[Test]
    public function it_records_who_changed_the_status(): void
    {
        $ticket = Ticket::factory()->create();
        $technician = User::factory()->create();

        $event = new TicketStatusChanged($ticket, TicketStatusEnum::Open, TicketStatusEnum::Closed, $technician);

        $this->assertSame($technician->id, $event->changedBy?->id);
    }

    #[Test]
    public function the_changing_user_is_optional(): void
    {
        $ticket = Ticket::factory()->create();
        $event = new TicketStatusChanged($ticket, TicketStatusEnum::Open, TicketStatusEnum::Closed);

        $this->assertNull($event->changedBy);
    }
}
