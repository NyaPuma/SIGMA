<?php

namespace Tests\Feature\Domain;

use App\Domain\Ticket\Actions\CancelTicketAction;
use App\Domain\Ticket\Actions\CloseTicketAction;
use App\Domain\Ticket\Actions\ReopenTicketAction;
use App\Domain\Ticket\Actions\StartTicketAction;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketStatusService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\DatabaseTestCase;
use Tests\Concerns\CreatesTickets;

class TicketLifecycleActionsTest extends DatabaseTestCase
{
    use CreatesTickets;

    private TicketStatusService $statusService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusService = app(TicketStatusService::class);
    }

    #[Test]
    public function cancel_action_sets_ticket_to_cancelled_and_closes_it(): void
    {
        $ticket = $this->createTicket();
        $action = app(CancelTicketAction::class);

        $result = $action->execute($ticket);

        $this->assertTrue($result);
        $this->assertTrue($ticket->fresh()->hasStatus(TicketStatusEnum::Cancelled));
        $this->assertNotNull($ticket->fresh()->closed_at);
    }

    #[Test]
    public function cancel_action_is_idempotent_when_already_cancelled(): void
    {
        $ticket = $this->createTicketWithStatus(TicketStatusEnum::Cancelled->value);
        $closedAt = $ticket->closed_at;
        $action = app(CancelTicketAction::class);

        $result = $action->execute($ticket);

        $this->assertTrue($result);
        $this->assertEquals($closedAt, $ticket->fresh()->closed_at);
    }

    #[Test]
    public function close_action_sets_cost_report_and_minutes_spent(): void
    {
        $ticket = $this->createTicket();
        $action = app(CloseTicketAction::class);

        $result = $action->execute($ticket, cost: 125.50, report: 'Fonte substituída', minutesSpent: 90);

        $this->assertTrue($result);
        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->hasStatus(TicketStatusEnum::Closed));
        $this->assertEquals(125.50, (float) $fresh->actual_cost);
        $this->assertEquals('Fonte substituída', $fresh->technical_report);
        $this->assertEquals(90, $fresh->minutes_spent);
        $this->assertNotNull($fresh->closed_at);
    }

    #[Test]
    public function close_action_preserves_existing_closed_at(): void
    {
        $ticket = $this->createTicket(['closed_at' => now()->subDays(1)]);
        $closedAt = $ticket->closed_at;
        $action = app(CloseTicketAction::class);

        $action->execute($ticket, cost: 10);

        $this->assertEquals($closedAt->toDateTimeString(), $ticket->fresh()->closed_at->toDateTimeString());
    }

    #[Test]
    public function close_action_is_idempotent_when_already_closed(): void
    {
        $ticket = $this->createTicketWithStatus(TicketStatusEnum::Closed->value);
        $action = app(CloseTicketAction::class);

        $result = $action->execute($ticket, cost: 999);

        $this->assertTrue($result);
        $this->assertNull($ticket->fresh()->actual_cost);
    }

    #[Test]
    public function reopen_action_requires_closed_ticket(): void
    {
        $ticket = $this->createTicket();
        $action = app(ReopenTicketAction::class);

        $this->assertFalse($action->execute($ticket));
        $this->assertTrue($ticket->fresh()->hasStatus(TicketStatusEnum::Open));
        $this->assertNull($ticket->fresh()->closed_at);
    }

    #[Test]
    public function reopen_action_returns_ticket_to_open_state(): void
    {
        $ticket = $this->createTicketWithStatus(TicketStatusEnum::Closed->value);
        $action = app(ReopenTicketAction::class);

        $result = $action->execute($ticket);

        $this->assertTrue($result);
        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->hasStatus(TicketStatusEnum::Open));
        $this->assertNull($fresh->closed_at);
        $this->assertNotNull($fresh->reopened_at);
    }

    #[Test]
    public function start_action_moves_ticket_to_in_progress_and_assigns_user(): void
    {
        $user = User::factory()->create();
        $ticket = $this->createTicket();
        $action = app(StartTicketAction::class);

        $result = $action->execute($ticket, $user);

        $this->assertTrue($result);
        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->hasStatus(TicketStatusEnum::InProgress));
        $this->assertEquals($user->id, $fresh->assigned_to);
        $this->assertNotNull($fresh->in_progress_at);
    }

    #[Test]
    public function start_action_is_idempotent_when_already_in_progress(): void
    {
        $ticket = $this->createTicketWithStatus(TicketStatusEnum::InProgress->value);
        $inProgressAt = $ticket->in_progress_at;
        $action = app(StartTicketAction::class);

        $result = $action->execute($ticket);

        $this->assertTrue($result);
        $this->assertEquals($inProgressAt, $ticket->fresh()->in_progress_at);
    }

    #[Test]
    public function start_action_keeps_existing_assignment_and_start_time(): void
    {
        $assigned = User::factory()->create();
        $ticket = $this->createTicket([
            'assigned_to' => $assigned->id,
            'in_progress_at' => now()->subDay(),
        ]);
        $action = app(StartTicketAction::class);

        $action->execute($ticket);

        $fresh = $ticket->fresh();
        $this->assertEquals($assigned->id, $fresh->assigned_to);
        $this->assertNotNull($fresh->in_progress_at);
    }

    #[Test]
    public function start_action_keeps_an_assignment_committed_after_the_copy_was_loaded(): void
    {
        $assigned = User::factory()->create();
        $claimer = User::factory()->create();
        $ticket = $this->createTicket();

        // A second request loaded the ticket before the assignment landed and knows nothing
        // about it; starting must not silently hand the ticket to whoever clicked last.
        $stale = Ticket::findOrFail($ticket->getKey());
        $ticket->update(['assigned_to' => $assigned->id]);

        $this->assertTrue(app(StartTicketAction::class)->execute($stale, $claimer));

        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->hasStatus(TicketStatusEnum::InProgress));
        $this->assertEquals($assigned->id, $fresh->assigned_to);
    }

    #[Test]
    public function cancel_action_keeps_the_close_date_written_by_another_request(): void
    {
        $ticket = $this->createTicket();
        $stale = Ticket::findOrFail($ticket->getKey());

        $closedAt = now()->subDays(2);
        $cancelledId = $this->statusService->getByName(TicketStatusEnum::Cancelled);
        Ticket::whereKey($ticket->getKey())->update(['status_id' => $cancelledId, 'closed_at' => $closedAt]);

        $this->assertTrue(app(CancelTicketAction::class)->execute($stale));

        // Re-cancelling an already cancelled ticket must not move its close date.
        $this->assertEquals(
            $closedAt->toDateTimeString(),
            $ticket->fresh()->closed_at->toDateTimeString(),
        );
    }

    #[Test]
    public function reopen_action_refuses_when_another_request_already_restarted_the_ticket(): void
    {
        $ticket = $this->createTicketWithStatus(TicketStatusEnum::Closed->value, ['closed_at' => now()]);
        $stale = Ticket::findOrFail($ticket->getKey());

        $inProgressId = $this->statusService->getByName(TicketStatusEnum::InProgress);
        Ticket::whereKey($ticket->getKey())->update(['status_id' => $inProgressId, 'closed_at' => null]);

        $this->assertFalse(app(ReopenTicketAction::class)->execute($stale));

        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->hasStatus(TicketStatusEnum::InProgress));
        $this->assertNull($fresh->reopened_at);
    }

    #[Test]
    public function close_action_leaves_the_callers_copy_describing_the_committed_row(): void
    {
        $ticket = $this->createTicket();
        $stale = Ticket::findOrFail($ticket->getKey());

        Ticket::whereKey($ticket->getKey())->update(['technical_report' => 'Relatório de outro pedido']);

        $this->assertTrue(app(CloseTicketAction::class)->execute($stale, cost: 50));

        // The controller broadcasts and serializes this same object afterwards, so it must
        // not keep holding the pre-transition snapshot.
        $this->assertSame('Relatório de outro pedido', $stale->technical_report);
        $this->assertTrue($stale->hasStatus(TicketStatusEnum::Closed));
        $this->assertEquals(50.0, (float) $stale->actual_cost);
    }

    #[Test]
    public function transitions_refresh_the_relations_cached_on_the_callers_copy(): void
    {
        $ticket = $this->createTicket();
        $late = User::factory()->create();

        // Controllers read $ticket->status (for the broadcast) and often $ticket->technician
        // before acting, which caches both relations on the very object they serialize
        // afterwards. Eloquent does not invalidate them when the keys move.
        $this->assertSame(TicketStatusEnum::Open->value, $ticket->status->name);
        $this->assertNull($ticket->technician);

        Ticket::whereKey($ticket->getKey())->update(['assigned_to' => $late->id]);

        $this->assertTrue(app(StartTicketAction::class)->execute($ticket));

        $this->assertSame(TicketStatusEnum::InProgress->value, $ticket->status->name);
        $this->assertEquals($late->id, $ticket->technician?->id);
    }

    #[Test]
    public function close_action_stamps_the_resolution_date_the_calendar_reads(): void
    {
        $ticket = $this->createTicket();

        $this->assertTrue(app(CloseTicketAction::class)->execute($ticket));

        $fresh = $ticket->fresh();
        $this->assertNotNull($fresh->resolved_at);
        $this->assertNotNull($fresh->closed_at);
    }

    #[Test]
    public function close_action_keeps_an_existing_resolution_date(): void
    {
        $resolvedAt = now()->subDays(4);
        $ticket = $this->createTicket(['resolved_at' => $resolvedAt]);

        $this->assertTrue(app(CloseTicketAction::class)->execute($ticket));

        $this->assertEquals($resolvedAt->toDateTimeString(), $ticket->fresh()->resolved_at->toDateTimeString());
    }

    #[Test]
    public function reopen_action_clears_the_resolution_date(): void
    {
        $ticket = $this->createTicketWithStatus(
            TicketStatusEnum::Closed->value,
            ['closed_at' => now()->subDay(), 'resolved_at' => now()->subDay()],
        );

        $this->assertTrue(app(ReopenTicketAction::class)->execute($ticket));

        $this->assertNull($ticket->fresh()->resolved_at);
    }
}
