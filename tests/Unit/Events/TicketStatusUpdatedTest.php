<?php

namespace Tests\Unit\Events;

use App\Enums\TicketStatusEnum;
use App\Events\TicketStatusUpdated;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\DatabaseTestCase;

class TicketStatusUpdatedTest extends DatabaseTestCase
{
    #[Test]
    public function it_keeps_the_ticket_and_normalizes_enum_statuses(): void
    {
        $ticket = Ticket::factory()->create();
        $event = new TicketStatusUpdated($ticket, TicketStatusEnum::Open, TicketStatusEnum::InProgress);

        $this->assertSame($ticket->id, $event->ticket->id);
        $this->assertSame(TicketStatusEnum::Open, $event->oldStatus);
        $this->assertSame(TicketStatusEnum::InProgress, $event->newStatus);
    }

    #[Test]
    public function it_normalizes_raw_status_strings(): void
    {
        $ticket = Ticket::factory()->create();
        $event = new TicketStatusUpdated($ticket, 'Aberto', 'Em Curso');

        $this->assertSame(TicketStatusEnum::Open, $event->oldStatus);
        $this->assertSame(TicketStatusEnum::InProgress, $event->newStatus);
    }

    #[Test]
    public function it_falls_back_to_open_for_an_unrecognized_status(): void
    {
        $ticket = Ticket::factory()->create();
        $event = new TicketStatusUpdated($ticket, 'não existe', 'Em Curso');

        $this->assertSame(TicketStatusEnum::Open, $event->oldStatus);
    }

    #[Test]
    public function it_defaults_the_change_timestamp_and_accepts_an_explicit_one(): void
    {
        $ticket = Ticket::factory()->create();

        $default = new TicketStatusUpdated($ticket, TicketStatusEnum::Open, TicketStatusEnum::Closed);
        $this->assertTrue($default->changedAt > CarbonImmutable::now()->subMinutes(1));

        $moment = CarbonImmutable::parse('2026-01-02 03:04:05');
        $explicit = new TicketStatusUpdated($ticket, TicketStatusEnum::Open, TicketStatusEnum::Closed, $moment);
        $this->assertTrue($explicit->changedAt->eq($moment));
    }
}
