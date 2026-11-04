<?php

namespace Tests\Unit\Listeners;

use App\Enums\TicketStatusEnum;
use App\Events\TicketStatusChanged;
use App\Listeners\LogTicketWorkflowChange;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogTicketWorkflowChangeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_logs_the_transition_with_valid_status_ids(): void
    {
        $open = TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Open->value], ['code' => 'ABERTA']);
        $inProgress = TicketStatus::firstOrCreate(['name' => TicketStatusEnum::InProgress->value], ['code' => 'EM_CURSO']);
        $ticket = Ticket::factory()->create(['status_id' => $open->id]);

        $listener = new LogTicketWorkflowChange;
        $listener->handle(new TicketStatusChanged(
            $ticket,
            TicketStatusEnum::Open,
            TicketStatusEnum::InProgress,
        ));

        $this->assertDatabaseHas('ticket_workflow_history', [
            'ticket_id' => $ticket->id,
            'origin_status_id' => $open->id,
            'destination_status_id' => $inProgress->id,
        ]);
    }

    #[Test]
    public function it_records_the_changed_by_user_as_technician(): void
    {
        $open = TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Open->value], ['code' => 'ABERTA']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::InProgress->value], ['code' => 'EM_CURSO']);
        $ticket = Ticket::factory()->create(['status_id' => $open->id]);
        $changedBy = User::factory()->create();

        $listener = new LogTicketWorkflowChange;
        $listener->handle(new TicketStatusChanged(
            $ticket,
            TicketStatusEnum::Open,
            TicketStatusEnum::InProgress,
            $changedBy,
        ));

        $this->assertDatabaseHas('ticket_workflow_history', [
            'ticket_id' => $ticket->id,
            'technician_id' => $changedBy->id,
        ]);
    }

    #[Test]
    public function it_falls_back_to_the_assigned_technician_when_no_actor_is_known(): void
    {
        $open = TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Open->value], ['code' => 'ABERTA']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::InProgress->value], ['code' => 'EM_CURSO']);
        $technician = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status_id' => $open->id,
            'assigned_to' => $technician->id,
        ]);

        $listener = new LogTicketWorkflowChange;
        $listener->handle(new TicketStatusChanged(
            $ticket,
            TicketStatusEnum::Open,
            TicketStatusEnum::InProgress,
        ));

        $this->assertDatabaseHas('ticket_workflow_history', [
            'ticket_id' => $ticket->id,
            'technician_id' => $technician->id,
        ]);
    }
}
