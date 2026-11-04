<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketWorkflowHistory;
use App\Models\User;
use App\Notifications\TicketStatusChanged as TicketStatusChangedNotification;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

/**
 * A status transition used to write several identical history rows and deliver the
 * "status changed" mail more than once: the framework's own event provider kept
 * discovering `app/Listeners`, so every listener was also registered as `Class@handle`
 * on top of the explicit map, and two listeners wrote the same transition from two
 * different events.
 */
final class TicketStatusHistoryUniquenessTest extends FeatureTestCase
{
    private function inProgressTicket(): Ticket
    {
        $owner = $this->createRegularUser(['email' => 'owner@example.test']);

        return Ticket::factory()->create([
            'user_id' => $owner->id,
            'priority' => TicketPriorityEnum::High->value,
            'status_id' => TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id'),
            'assigned_to' => $this->createTechnician(['email' => 'tech@example.test'])->id,
        ]);
    }

    #[Test]
    public function a_model_level_status_change_writes_a_single_history_row(): void
    {
        Notification::fake();
        $ticket = $this->inProgressTicket();

        $ticket->update(['status_id' => TicketStatus::where('name', TicketStatusEnum::Closed->value)->value('id')]);

        $this->assertSame(1, TicketWorkflowHistory::where('ticket_id', $ticket->id)->count());
    }

    #[Test]
    public function closing_a_ticket_writes_a_single_history_row_and_notifies_the_owner_once(): void
    {
        Notification::fake();
        app(TicketStatusService::class)->flush();
        $ticket = $this->inProgressTicket();

        /** @var User $technician */
        $technician = $ticket->technician;

        $response = $this->withHeaders(['X-Auth-Token' => $technician->api_token])
            ->postJson(route('tickets.close', $ticket), ['actual_cost' => 12.5]);

        $response->assertSuccessful();

        $this->assertSame(1, TicketWorkflowHistory::where('ticket_id', $ticket->id)->count());

        Notification::assertSentToTimes(
            $ticket->fresh()->user,
            TicketStatusChangedNotification::class,
            1,
        );
    }

    #[Test]
    public function the_history_row_is_attributed_to_the_user_who_made_the_transition(): void
    {
        Notification::fake();
        $ticket = $this->inProgressTicket();
        $admin = $this->createAdmin(['email' => 'admin@example.test']);

        $this->actingAs($admin);
        $ticket->update(['status_id' => TicketStatus::where('name', TicketStatusEnum::Closed->value)->value('id')]);

        $this->assertSame(
            [$admin->id],
            TicketWorkflowHistory::where('ticket_id', $ticket->id)->pluck('technician_id')->all(),
        );
    }
}
