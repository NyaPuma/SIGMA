<?php

namespace Tests\Unit\Models;

use App\Actions\ApproveBudgetAction;
use App\DTOs\BudgetDecisionData;
use App\Enums\BudgetDecisionEnum;
use App\Enums\BudgetStatusEnum;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\TicketWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLookupData();
    }

    private function seedLookupData(): void
    {
        TicketType::firstOrCreate(['name' => 'avaria', 'description' => 'Avaria'], ['code' => 'AVARIA']);
        TicketType::firstOrCreate(['name' => 'preventiva', 'description' => 'Manutenção Preventiva'], ['code' => 'PREVENTIVA']);

        $typeId = TicketType::where('name', 'avaria')->first()->id;
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Open->value], ['code' => 'ABERTA', 'description' => 'Aberto', 'type_id' => $typeId]);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::InProgress->value], ['code' => 'EM_CURSO', 'description' => 'Em Curso', 'type_id' => $typeId]);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Closed->value], ['code' => 'FECHADA', 'description' => 'Fechado', 'type_id' => $typeId]);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Cancelled->value], ['code' => 'CANCELADA', 'description' => 'Cancelado', 'type_id' => $typeId]);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::PendingBudget->value], ['code' => 'PENDENTE_ORCAMENTO', 'description' => 'Pendente Orçamento', 'type_id' => $typeId]);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Rejected->value], ['code' => 'RECUSADA', 'description' => 'Recusada', 'type_id' => $typeId]);

        UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
        UserProfile::firstOrCreate(['name' => UserRoleEnum::Technician->value]);
        UserProfile::firstOrCreate(['name' => UserRoleEnum::Admin->value]);
    }

    #[Test]
    public function it_starts_repair_correctly(): void
    {
        $user = User::factory()->create();
        $openStatusId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');

        $ticket = Ticket::create([
            'title' => 'Repair Ticket',
            'description' => 'Testing repair start',
            'priority' => TicketPriorityEnum::High->value,
            'user_id' => $user->id,
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $result = app(TicketWorkflowService::class)->startRepair($ticket);

        $this->assertTrue($result);
        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::InProgress));
        $this->assertNotNull($ticket->in_progress_at);
    }

    #[Test]
    public function it_reopens_a_closed_ticket_correctly(): void
    {
        $user = User::factory()->create();
        $closedStatusId = TicketStatus::where('name', TicketStatusEnum::Closed->value)->value('id');

        $ticket = Ticket::create([
            'title' => 'Reopen Test',
            'description' => 'Testing reopen',
            'priority' => TicketPriorityEnum::Medium->value,
            'user_id' => $user->id,
            'status_id' => $closedStatusId,
            'closed_at' => now(),
            'opened_at' => now()->subDays(1),
        ]);

        $result = app(TicketWorkflowService::class)->reopen($ticket);

        $this->assertTrue($result);
        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Open));
        $this->assertNull($ticket->closed_at);
        $this->assertNotNull($ticket->reopened_at);
    }

    #[Test]
    public function it_rejects_reopen_for_non_closed_tickets(): void
    {
        $user = User::factory()->create();
        $openStatusId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');

        $ticket = Ticket::create([
            'title' => 'Non Closed Reopen',
            'description' => 'Should not reopen',
            'priority' => TicketPriorityEnum::Low->value,
            'user_id' => $user->id,
            'status_id' => $openStatusId,
            'opened_at' => now(),
        ]);

        $result = app(TicketWorkflowService::class)->reopen($ticket);
        $this->assertFalse($result);
    }

    #[Test]
    public function it_auto_closes_ticket_below_threshold(): void
    {
        $user = User::factory()->create();
        $inProgressStatusId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');

        $ticket = Ticket::create([
            'title' => 'Auto Close Test',
            'description' => 'Testing auto close',
            'priority' => TicketPriorityEnum::Medium->value,
            'user_id' => $user->id,
            'status_id' => $inProgressStatusId,
            'estimated_cost' => 50.00,
            'opened_at' => now(),
            'in_progress_at' => now(),
        ]);

        $result = app(TicketWorkflowService::class)->checkAutoClose($ticket, 100.00);

        $this->assertTrue($result);
        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Closed));
        $this->assertNotNull($ticket->closed_at);
    }

    #[Test]
    public function it_skips_auto_close_when_cost_exceeds_threshold(): void
    {
        $user = User::factory()->create();
        $inProgressStatusId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');

        $ticket = Ticket::create([
            'title' => 'Skip Auto Close',
            'description' => 'Cost exceeds threshold',
            'priority' => TicketPriorityEnum::High->value,
            'user_id' => $user->id,
            'status_id' => $inProgressStatusId,
            'estimated_cost' => 200.00,
            'opened_at' => now(),
            'in_progress_at' => now(),
        ]);

        $result = app(TicketWorkflowService::class)->checkAutoClose($ticket, 100.00);

        $this->assertFalse($result);
        $ticket->refresh();
        $this->assertFalse($ticket->hasStatus(TicketStatusEnum::Closed));
    }

    #[Test]
    public function it_approves_budget_as_admin(): void
    {
        $adminProfile = UserProfile::where('name', UserRoleEnum::Admin->value)->first();
        $admin = User::factory()->create(['profile_id' => $adminProfile->id]);

        $pendingBudgetStatusId = TicketStatus::where('name', TicketStatusEnum::PendingBudget->value)->value('id');
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'title' => 'Approve Budget',
            'description' => 'Testing budget approval',
            'priority' => TicketPriorityEnum::High->value,
            'user_id' => $user->id,
            'status_id' => $pendingBudgetStatusId,
            'opened_at' => now(),
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
            'budget_amount' => 500.00,
            'budget_requested_at' => now(),
        ]);

        $data = new BudgetDecisionData(decision: BudgetDecisionEnum::Approve);
        $result = app(ApproveBudgetAction::class)->execute($ticket, $admin, $data);

        $this->assertInstanceOf(Ticket::class, $result);
        $this->assertEquals(BudgetStatusEnum::Approved->value, $ticket->budget_status);
        $this->assertEquals($admin->id, $ticket->budget_approved_by);
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::InProgress));
        $this->assertNotNull($ticket->budget_decided_at);
    }

    #[Test]
    public function it_rejects_budget_as_admin(): void
    {
        $adminProfile = UserProfile::where('name', UserRoleEnum::Admin->value)->first();
        $admin = User::factory()->create(['profile_id' => $adminProfile->id]);

        $pendingBudgetStatusId = TicketStatus::where('name', TicketStatusEnum::PendingBudget->value)->value('id');
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'title' => 'Reject Budget',
            'description' => 'Testing budget rejection',
            'priority' => TicketPriorityEnum::Medium->value,
            'user_id' => $user->id,
            'status_id' => $pendingBudgetStatusId,
            'opened_at' => now(),
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
            'budget_amount' => 1200.00,
            'budget_requested_at' => now(),
        ]);

        $data = new BudgetDecisionData(decision: BudgetDecisionEnum::Reject, feedback: 'Orçamento demasiado alto');
        $result = app(ApproveBudgetAction::class)->execute($ticket, $admin, $data);

        $this->assertInstanceOf(Ticket::class, $result);
        $this->assertEquals(BudgetStatusEnum::Rejected->value, $ticket->budget_status);
        $this->assertEquals($admin->id, $ticket->budget_approved_by);
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Rejected));
        $this->assertEquals('Orçamento demasiado alto', $ticket->budget_feedback);
    }

    #[Test]
    public function it_rejects_budget_approval_from_non_admin(): void
    {
        $operatorProfile = UserProfile::where('name', UserRoleEnum::User->value)->first();
        $operator = User::factory()->create(['profile_id' => $operatorProfile->id]);

        $pendingBudgetStatusId = TicketStatus::where('name', TicketStatusEnum::PendingBudget->value)->value('id');
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'title' => 'Non Admin Budget',
            'description' => 'Should reject',
            'priority' => TicketPriorityEnum::Low->value,
            'user_id' => $user->id,
            'status_id' => $pendingBudgetStatusId,
            'opened_at' => now(),
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
            'budget_amount' => 200.00,
            'budget_requested_at' => now(),
        ]);

        $this->assertFalse($operator->can('approveBudget', $ticket));
    }
}
