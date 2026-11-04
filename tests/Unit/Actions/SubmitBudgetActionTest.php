<?php

namespace Tests\Unit\Actions;

use App\Actions\SubmitBudgetAction;
use App\DTOs\BudgetSubmissionData;
use App\Enums\BudgetStatusEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketStatusService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;
use Tests\Concerns\CreatesTickets;

class SubmitBudgetActionTest extends FeatureTestCase
{
    use CreatesTickets;

    private SubmitBudgetAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        app(TicketStatusService::class)->flush();

        $this->action = app(SubmitBudgetAction::class);
    }

    #[Test]
    public function it_submits_a_budget_for_an_open_ticket(): void
    {
        $ticket = $this->createTicket();

        $result = $this->action->execute(
            $ticket,
            new BudgetSubmissionData(estimatedBudget: 150.00)
        );

        $this->assertTrue($result->budget_requested);
        $this->assertEquals(BudgetStatusEnum::Pending->value, $result->budget_status);
        $this->assertEquals(150.00, $result->budget_amount);
        $this->assertNotNull($result->budget_requested_at);
    }

    #[Test]
    public function it_stores_detailed_budget_as_json(): void
    {
        $ticket = $this->createTicket();
        $details = [
            ['item' => 'Peça A', 'quantity' => 2, 'unit_price' => 50.00],
            ['item' => 'Mão de obra', 'quantity' => 1, 'unit_price' => 100.00],
        ];

        $result = $this->action->execute(
            $ticket,
            new BudgetSubmissionData(estimatedBudget: 200.00, budgetDetails: $details)
        );

        // 50.00 round-trips through JSON as the int 50, so compare loosely.
        $this->assertEquals($details, $result->budget_details);

        // Ticket::$casts already encodes the array on save. Encoding it here as well would put a
        // JSON string inside the JSON, and every reader would get text instead of the item list.
        $raw = Ticket::find($result->id)->getRawOriginal('budget_details');
        $this->assertEquals($details, json_decode((string) $raw, true));
    }

    #[Test]
    public function it_resets_previous_budget_feedback_on_resubmission(): void
    {
        $ticket = $this->createTicketWithStatus(TicketStatusEnum::Open->value, ['budget_feedback' => 'Orçamento rejeitado anteriormente']);

        $result = $this->action->execute(
            $ticket,
            new BudgetSubmissionData(estimatedBudget: 300.00)
        );

        $this->assertNull($result->budget_feedback);
    }

    #[Test]
    public function it_rejects_submitting_a_budget_for_a_closed_ticket(): void
    {
        $ticket = $this->createTicketWithStatus(TicketStatusEnum::Closed->value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot submit a budget for a ticket that is already closed.');

        $this->action->execute(
            $ticket,
            new BudgetSubmissionData(estimatedBudget: 100.00)
        );
    }

    #[Test]
    public function it_rejects_a_second_budget_while_one_is_pending(): void
    {
        $ticket = $this->createTicketWithBudget();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A pending budget request already exists for this ticket.');

        $this->action->execute(
            $ticket,
            new BudgetSubmissionData(estimatedBudget: 500.00)
        );
    }

    #[Test]
    public function it_rejects_a_budget_estimate_of_zero(): void
    {
        $ticket = $this->createTicket();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The budget amount must be greater than 0.');

        $this->action->execute(
            $ticket,
            new BudgetSubmissionData(estimatedBudget: 0.0)
        );
    }

    #[Test]
    public function it_loads_technician_status_and_user_on_the_updated_ticket(): void
    {
        $ticket = $this->createTicket();

        $result = $this->action->execute(
            $ticket,
            new BudgetSubmissionData(estimatedBudget: 80.00)
        );

        $this->assertTrue($result->relationLoaded('technician'));
        $this->assertTrue($result->relationLoaded('status'));
        $this->assertTrue($result->relationLoaded('user'));
    }

    #[Test]
    public function it_refuses_to_submit_over_a_request_committed_after_the_copy_was_loaded(): void
    {
        $ticket = $this->createTicket();

        // A colleague's request was committed after this copy was read; submitting from the
        // stale copy would replace their amount and leave two requests claiming one ticket.
        $stale = Ticket::findOrFail($ticket->getKey());
        $ticket->update([
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
            'budget_amount' => 250.00,
            'budget_requested_at' => now(),
        ]);

        try {
            $this->action->execute($stale, new BudgetSubmissionData(estimatedBudget: 400.00));
            $this->fail('Expected the pending request to be detected.');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('A pending budget request already exists for this ticket.', $e->getMessage());
        }

        $this->assertEquals(250.00, $ticket->fresh()->budget_amount);
    }

    #[Test]
    public function it_refuses_to_submit_for_a_ticket_closed_while_the_copy_was_in_memory(): void
    {
        $ticket = $this->createTicket();
        $stale = Ticket::findOrFail($ticket->getKey());

        $ticket->update([
            'status_id' => app(TicketStatusService::class)->getByName(TicketStatusEnum::Closed),
        ]);

        try {
            $this->action->execute($stale, new BudgetSubmissionData(estimatedBudget: 120.00));
            $this->fail('Expected the closed ticket to be detected.');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('Cannot submit a budget for a ticket that is already closed.', $e->getMessage());
        }

        $this->assertNull($ticket->fresh()->budget_status);
    }

    #[Test]
    public function it_clears_the_previous_decision_when_a_new_request_is_submitted(): void
    {
        $admin = User::factory()->create();
        $ticket = $this->createTicketWithStatus(
            TicketStatusEnum::InProgress->value,
            [
                'budget_requested' => true,
                'budget_status' => BudgetStatusEnum::Approved->value,
                'budget_amount' => 100.00,
                'budget_requested_at' => now()->subDays(2),
                'budget_approved_by' => $admin->id,
                'budget_decided_at' => now()->subDay(),
            ]
        );

        $result = $this->action->execute($ticket, new BudgetSubmissionData(estimatedBudget: 300.00));

        $this->assertEquals(BudgetStatusEnum::Pending->value, $result->budget_status);
        // The stored decision belonged to a request that no longer exists: keeping it would
        // attribute this pending request to that admin and skew the budget-pause KPI.
        $this->assertNull($result->budget_approved_by);
        $this->assertNull($result->budget_decided_at);
    }
}
