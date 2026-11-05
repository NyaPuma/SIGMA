<?php

namespace Tests\Unit\Concerns;

use App\Concerns\NotifiesTicketStatusChange;
use App\Enums\TicketStatusEnum;
use App\Events\TicketStatusUpdated;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class NotifiesTicketStatusChangeTest extends FeatureTestCase
{
    private function makeSubject(): object
    {
        return new class
        {
            use NotifiesTicketStatusChange;

            public function fire(
                Ticket $ticket,
                TicketStatusEnum|TicketStatus|string|null $old,
                TicketStatusEnum|TicketStatus|string|null $new
            ): void {
                $this->notifyStatusChange($ticket, $old, $new);
            }
        };
    }

    #[Test]
    public function it_accepts_status_models_captured_before_the_transition(): void
    {
        Event::fake([TicketStatusUpdated::class]);
        Notification::fake();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $oldStatus = TicketStatus::where('name', TicketStatusEnum::Open->value)->firstOrFail();
        $newStatus = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->firstOrFail();

        $this->makeSubject()->fire($ticket, $oldStatus, $newStatus);

        Event::assertDispatched(TicketStatusUpdated::class, function ($event) {
            return $event->oldStatus === TicketStatusEnum::Open
                && $event->newStatus === TicketStatusEnum::InProgress;
        });
    }

    #[Test]
    public function it_dispatches_the_status_update_event_for_the_status_transition(): void
    {
        Event::fake([TicketStatusUpdated::class]);
        Notification::fake();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->makeSubject()->fire($ticket, TicketStatusEnum::Open, TicketStatusEnum::InProgress);

        Event::assertDispatched(TicketStatusUpdated::class, function ($event) use ($ticket) {
            return $event->ticket->id === $ticket->id
                && $event->oldStatus === TicketStatusEnum::Open
                && $event->newStatus === TicketStatusEnum::InProgress;
        });

        // Delivery belongs to SendTicketStatusNotification: notifying from here as well
        // made every transition reach the owner twice.
        Notification::assertNothingSent();
    }

    #[Test]
    public function it_accepts_plain_string_status_values(): void
    {
        Event::fake([TicketStatusUpdated::class]);
        Notification::fake();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->makeSubject()->fire($ticket, TicketStatusEnum::Open->value, TicketStatusEnum::InProgress->value);

        Event::assertDispatched(TicketStatusUpdated::class, function ($event) {
            return $event->oldStatus === TicketStatusEnum::Open
                && $event->newStatus === TicketStatusEnum::InProgress;
        });
    }

    #[Test]
    public function it_still_dispatches_when_the_ticket_has_no_owner(): void
    {
        Event::fake([TicketStatusUpdated::class]);
        Notification::fake();

        $ticket = Ticket::factory()->create();
        $ticket->setRelation('user', null);

        $this->makeSubject()->fire($ticket, TicketStatusEnum::Open, TicketStatusEnum::Closed);

        Event::assertDispatched(TicketStatusUpdated::class);
    }
}
