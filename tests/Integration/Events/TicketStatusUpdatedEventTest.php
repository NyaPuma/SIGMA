<?php

namespace Tests\Integration\Events;

use App\Events\TicketStatusUpdated;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketNotification;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The ticket controllers dispatch `TicketStatusUpdated` after a transition; this proves
 * the mapping in `EventServiceProvider` still delivers it to both notification listeners.
 * It used to sit in `tests/Integration/Broadcasting` and assert a WebSocket payload that
 * no client ever read.
 */
class TicketStatusUpdatedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_status_update_event_reaches_both_notification_listeners(): void
    {
        Notification::fake();

        /** @var User $owner */
        $owner = User::factory()->create();

        /** @var User $technician */
        $technician = User::factory()->create();

        /** @var Ticket $ticket */
        $ticket = Ticket::factory()->create([
            'user_id' => $owner->id,
            'assigned_to' => $technician->id,
        ]);

        event(new TicketStatusUpdated($ticket, 'Aberto', 'Em Curso'));

        Notification::assertSentTo($owner, TicketStatusChanged::class);
        Notification::assertSentTo($technician, NewTicketNotification::class);
    }
}
