<?php

namespace Tests\Feature\Actions;

use App\Actions\CreateTicketAction;
use App\DTOs\CreateTicketData;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Models\User;
use App\Services\TicketStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateTicketActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_ticket(): void
    {
        $user = User::factory()->create();
        $action = new CreateTicketAction(app(TicketStatusService::class));

        $data = new CreateTicketData(
            title: '  Test Ticket  ',
            description: '  Test Description  ',
            priority: TicketPriorityEnum::High,
            equipmentId: null,
            roomId: null,
        );

        $ticket = $action->execute($user, $data);

        $this->assertDatabaseHas('tickets', [
            'title' => 'Test Ticket',
            'description' => 'Test Description',
            'user_id' => $user->id,
            'priority' => TicketPriorityEnum::High->value,
        ]);

        $this->assertNotNull($ticket->status_id);
        $this->assertNotNull($ticket->opened_at);
        $this->assertStringStartsWith('TKT-', $ticket->reference);
        $this->assertSame(TicketStatusEnum::Open->value, $ticket->status->name);
    }
}