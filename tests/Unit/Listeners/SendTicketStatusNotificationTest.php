<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Enums\TicketStatusEnum;
use App\Events\TicketStatusUpdated;
use App\Listeners\SendTicketStatusNotification;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * This listener is the only sender of the "status changed" mail. The
 * `NotifiesTicketStatusChange` trait used to notify the owner directly as well, which
 * delivered the same message twice for every transition.
 */
class SendTicketStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_notifies_the_ticket_owner_exactly_once(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $ticket = Ticket::factory()->create(['user_id' => $owner->id]);

        // Faked after creation so the TicketCreated mail is not counted here.
        Notification::fake();

        $listener = new SendTicketStatusNotification;
        $listener->handle(new TicketStatusUpdated(
            $ticket,
            TicketStatusEnum::Open,
            TicketStatusEnum::Closed,
        ));

        Notification::assertSentToTimes($owner, TicketStatusChanged::class, 1);
    }

    #[Test]
    public function it_does_nothing_when_the_ticket_has_no_owner(): void
    {
        $ticket = Ticket::factory()->create();
        $ticket->setRelation('user', null);

        Notification::fake();

        $listener = new SendTicketStatusNotification;
        $listener->handle(new TicketStatusUpdated(
            $ticket,
            TicketStatusEnum::Open,
            TicketStatusEnum::InProgress,
        ));

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_does_nothing_when_the_owner_has_no_email(): void
    {
        // `users.email` is NOT NULL, so an address that is merely empty is the only
        // way this guard can be reached.
        $owner = User::factory()->create(['email' => '']);
        $ticket = Ticket::factory()->create(['user_id' => $owner->id]);

        Notification::fake();

        $listener = new SendTicketStatusNotification;
        $listener->handle(new TicketStatusUpdated(
            $ticket,
            TicketStatusEnum::Open,
            TicketStatusEnum::InProgress,
        ));

        Notification::assertNothingSent();
    }
}
