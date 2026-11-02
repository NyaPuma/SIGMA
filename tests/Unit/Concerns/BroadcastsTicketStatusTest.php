<?php

namespace Tests\Unit\Concerns;

use App\Concerns\BroadcastsTicketStatus;
use App\Enums\TicketStatusEnum;
use App\Events\TicketStatusUpdatedBroadcast;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class BroadcastsTicketStatusTest extends FeatureTestCase
{
    private function makeSubject(): object
    {
        return new class
        {
            use BroadcastsTicketStatus;

            public function fire(
                Ticket $ticket,
                TicketStatusEnum|TicketStatus|string|null $old,
                TicketStatusEnum|TicketStatus|string|null $new
            ): void {
                $this->broadcastStatusChange($ticket, $old, $new);
            }
        };
    }

    #[Test]
    public function it_accepts_status_models_captured_before_the_transition(): void
    {
        Event::fake([TicketStatusUpdatedBroadcast::class]);
        Notification::fake();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $oldStatus = TicketStatus::where('name', TicketStatusEnum::Open->value)->firstOrFail();
        $newStatus = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->firstOrFail();

        $this->makeSubject()->fire($ticket, $oldStatus, $newStatus);

        Event::assertDispatched(TicketStatusUpdatedBroadcast::class, function ($event) {
            return $event->oldStatus === TicketStatusEnum::Open
                && $event->newStatus === TicketStatusEnum::InProgress;
        });

        Notification::assertSentTo($user, TicketStatusChanged::class);
    }

    #[Test]
    public function it_dispatches_broadcast_event_and_notifies_ticket_owner(): void
    {
        Event::fake([TicketStatusUpdatedBroadcast::class]);
        Notification::fake();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->makeSubject()->fire($ticket, TicketStatusEnum::Open, TicketStatusEnum::InProgress);

        Event::assertDispatched(TicketStatusUpdatedBroadcast::class, function ($event) use ($ticket) {
            return $event->ticket->id === $ticket->id
                && $event->oldStatus === TicketStatusEnum::Open
                && $event->newStatus === TicketStatusEnum::InProgress;
        });

        Notification::assertSentTo($user, TicketStatusChanged::class);
    }

    #[Test]
    public function it_accepts_plain_string_status_values(): void
    {
        Event::fake([TicketStatusUpdatedBroadcast::class]);
        Notification::fake();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->makeSubject()->fire($ticket, TicketStatusEnum::Open->value, TicketStatusEnum::InProgress->value);

        Event::assertDispatched(TicketStatusUpdatedBroadcast::class, function ($event) {
            return $event->oldStatus === TicketStatusEnum::Open
                && $event->newStatus === TicketStatusEnum::InProgress;
        });
    }

    #[Test]
    public function it_skips_notification_when_ticket_has_no_owner(): void
    {
        Event::fake([TicketStatusUpdatedBroadcast::class]);
        Notification::fake();

        $ticket = Ticket::factory()->create();
        $ticket->setRelation('user', null);

        $this->makeSubject()->fire($ticket, TicketStatusEnum::Open, TicketStatusEnum::Closed);

        Event::assertDispatched(TicketStatusUpdatedBroadcast::class);
        Notification::assertNothingSent();
    }
}
