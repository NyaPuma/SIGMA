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

    public function test_generated_reference_fits_the_thirty_character_column(): void
    {
        $user = User::factory()->create();
        $action = new CreateTicketAction(app(TicketStatusService::class));

        $ticket = $action->execute($user, new CreateTicketData(
            title: 'Referência longa',
            description: 'Descrição',
            priority: TicketPriorityEnum::Low,
            equipmentId: null,
            roomId: null,
        ));

        // tickets.reference is VARCHAR(30), so an over-long value is a hard error on MySQL and
        // PostgreSQL; SQLite ignores the limit, which is why the suite alone never caught it.
        $this->assertLessThanOrEqual(30, strlen((string) $ticket->reference));
    }
}
