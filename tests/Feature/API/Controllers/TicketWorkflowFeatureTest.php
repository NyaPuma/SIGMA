<?php

namespace Tests\Feature\API\Controllers;

use App\Enums\NotificationTypeEnum;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\TicketStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketWorkflowFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        UserProfile::create(['name' => UserRoleEnum::User->value]);
        UserProfile::create(['name' => UserRoleEnum::Technician->value]);
        UserProfile::create(['name' => UserRoleEnum::Admin->value]);
        $this->artisan('db:seed', ['--class' => 'TicketLookupSeeder', '--force' => true]);

        // Flush the status cache to ensure fresh data for each test
        app(TicketStatusService::class)->flush();
    }

    private function createUserWithToken(string $profileName, bool $active = true): User
    {
        $profile = UserProfile::where('name', $profileName)->firstOrFail();

        return User::factory()->create([
            'profile_id' => $profile->id,
            'api_token' => Str::random(60),
            'active' => $active,
        ]);
    }

    private function createTicket(User $user, array $overrides = []): Ticket
    {
        $data = array_merge([
            'user_id' => $user->id,
            'title' => 'Test equipment fault',
            'description' => 'Equipment making unusual noise',
            'priority' => TicketPriorityEnum::High->value,
            'status_id' => TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id'),
            'opened_at' => now(),
        ], $overrides);

        return Ticket::create($data);
    }

    public function test_technician_can_start_ticket(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $ticket = $this->createTicket($user);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/start");

        $response->assertOk()
            ->assertJsonStructure(['ticket' => [
                'id', 'status_id', 'assigned_to', 'in_progress_at',
            ]]);

        $ticket->refresh();
        $this->assertEquals($technician->id, $ticket->assigned_to);
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::InProgress));
        $this->assertNotNull($ticket->in_progress_at);
    }

    public function test_non_technician_cannot_start_ticket(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $ticket = $this->createTicket($user);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/start");

        $response->assertStatus(403);
    }

    public function test_cannot_start_non_open_ticket(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');
        $ticket = $this->createTicket($user, ['status_id' => $inProgressId]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/start");

        $response->assertStatus(422);
    }

    public function test_start_returns_409_with_higher_priority_pending(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $openId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');

        $ticket = $this->createTicket($user, [
            'priority' => TicketPriorityEnum::Medium->value,
        ]);

        $this->createTicket($user, [
            'priority' => TicketPriorityEnum::Critical->value,
            'status_id' => $openId,
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/start");

        $response->assertStatus(409)
            ->assertJsonPath('warning', true)
            ->assertJsonPath('can_force', true)
            ->assertJsonPath('urgent_tickets_count', 1);

        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Open));
        $this->assertNull($ticket->assigned_to);
    }

    public function test_start_with_force_starts_despite_higher_priority(): void
    {
        $this->createUserWithToken(UserRoleEnum::Admin->value);
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $openId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');

        $ticket = $this->createTicket($user, [
            'priority' => TicketPriorityEnum::Medium->value,
        ]);

        $this->createTicket($user, [
            'priority' => TicketPriorityEnum::Critical->value,
            'status_id' => $openId,
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/start", [
                'force' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('overridden', true);

        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::InProgress));
        $this->assertEquals($technician->id, $ticket->assigned_to);

        $this->assertDatabaseHas('notifications', [
            'type' => NotificationTypeEnum::PriorityOverride->value,
        ]);
    }

    public function test_technician_can_close_ticket(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');
        $ticket = $this->createTicket($user, [
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now()->subHours(2),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close", [
                'minutes_spent' => 120,
                'cost' => 150.50,
                'technical_report' => 'Replaced faulty bearing',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['ticket' => [
                'id', 'status_id', 'closed_at', 'minutes_spent',
            ]]);

        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Closed));
        $this->assertEquals(120, $ticket->minutes_spent);
        $this->assertEquals(150.50, (float) $ticket->actual_cost);
        $this->assertEquals('Replaced faulty bearing', $ticket->technical_report);
    }

    public function test_closing_without_cost_or_minutes_keeps_what_the_technician_already_recorded(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');
        $ticket = $this->createTicket($user, [
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now()->subHours(3),
            'minutes_spent' => 240,
            'actual_cost' => 87.25,
            'technical_report' => 'Diagnóstico feito na véspera',
        ]);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close")
            ->assertOk();

        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Closed));
        $this->assertEquals(240, $ticket->minutes_spent);
        $this->assertEquals(87.25, (float) $ticket->actual_cost);
        $this->assertSame('Diagnóstico feito na véspera', $ticket->technical_report);
    }

    public function test_cannot_close_non_in_progress_ticket(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $ticket = $this->createTicket($user, ['assigned_to' => $technician->id]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close");

        $response->assertStatus(422);
    }

    public function test_technician_cannot_start_a_ticket_assigned_to_another_technician(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $owner = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $other = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $ticket = $this->createTicket($user, ['assigned_to' => $owner->id]);

        $this->withHeader('X-Auth-Token', $other->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/start")
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $owner->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/start")
            ->assertOk();
    }

    public function test_technician_cannot_close_a_ticket_assigned_to_another_technician(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $owner = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $other = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');
        $ticket = $this->createTicket($user, [
            'status_id' => $inProgressId,
            'assigned_to' => $owner->id,
            'in_progress_at' => now()->subHour(),
        ]);

        $this->withHeader('X-Auth-Token', $other->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close", ['minutes_spent' => 60])
            ->assertStatus(403);

        $this->withHeader('X-Auth-Token', $owner->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close", ['minutes_spent' => 60])
            ->assertOk();
    }

    public function test_technician_can_reopen_closed_ticket(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $closedId = TicketStatus::where('name', TicketStatusEnum::Closed->value)->value('id');
        $ticket = $this->createTicket($user, [
            'status_id' => $closedId,
            'closed_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->postJson("/api/tickets/{$ticket->id}/reopen");

        $response->assertOk()
            ->assertJsonStructure(['ticket' => ['id', 'status_id']]);

        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Open));
        $this->assertNotNull($ticket->reopened_at);
    }

    public function test_cannot_reopen_non_closed_ticket(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $ticket = $this->createTicket($user);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->postJson("/api/tickets/{$ticket->id}/reopen");

        $response->assertStatus(422);
    }

    public function test_technician_can_reopen_cancelled_ticket(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $cancelledId = TicketStatus::where('name', TicketStatusEnum::Cancelled->value)->value('id');
        $ticket = $this->createTicket($user, [
            'status_id' => $cancelledId,
            'closed_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->postJson("/api/tickets/{$ticket->id}/reopen");

        $response->assertOk()
            ->assertJsonPath('ticket.status_name', TicketStatusEnum::Open->value);

        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Open));
        $this->assertNull($ticket->closed_at);
    }

    public function test_reopen_response_reflects_open_status(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $closedId = TicketStatus::where('name', TicketStatusEnum::Closed->value)->value('id');
        $ticket = $this->createTicket($user, [
            'status_id' => $closedId,
            'closed_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->postJson("/api/tickets/{$ticket->id}/reopen");

        $response->assertOk()
            ->assertJsonPath('ticket.status_name', TicketStatusEnum::Open->value)
            ->assertJsonPath('ticket.status_id', app(TicketStatusService::class)->getByName(TicketStatusEnum::Open));
    }

    public function test_cancel_response_reflects_cancelled_status(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $ticket = $this->createTicket($user);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson("/api/tickets/{$ticket->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('ticket.status_name', TicketStatusEnum::Cancelled->value)
            ->assertJsonPath('ticket.status_id', app(TicketStatusService::class)->getByName(TicketStatusEnum::Cancelled));
    }

    public function test_user_can_cancel_own_ticket(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $ticket = $this->createTicket($user);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson("/api/tickets/{$ticket->id}/cancel");

        $response->assertOk();
        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Cancelled));
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_user_cannot_cancel_another_users_ticket(): void
    {
        $user1 = $this->createUserWithToken(UserRoleEnum::User->value);
        $user2 = $this->createUserWithToken(UserRoleEnum::User->value);
        $ticket = $this->createTicket($user1);

        $response = $this->withHeader('X-Auth-Token', $user2->api_token)
            ->postJson("/api/tickets/{$ticket->id}/cancel");

        $response->assertStatus(403);
    }

    public function test_technician_can_close_final(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');
        $ticket = $this->createTicket($user, [
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now()->subHours(3),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close-final", [
                'actual_cost' => 250.75,
                'report' => 'Replaced the motherboard and tested.',
                'minutes_spent' => 95,
            ]);

        $response->assertOk()
            ->assertJsonStructure(['ticket' => [
                'id', 'status_id', 'closed_at', 'minutes_spent',
            ]]);

        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Closed));
        $this->assertEquals(250.75, (float) $ticket->actual_cost);
        $this->assertEquals('Replaced the motherboard and tested.', $ticket->technical_report);
        $this->assertEquals(95, $ticket->minutes_spent);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_close_final_without_report_preserves_existing_report(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');
        $ticket = $this->createTicket($user, [
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now()->subHours(2),
            'technical_report' => 'Relatório parcial já registado.',
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close-final", [
                'actual_cost' => 120.00,
            ]);

        $response->assertOk();
        $ticket->refresh();
        $this->assertEquals('Relatório parcial já registado.', $ticket->technical_report);
    }

    public function test_close_final_returns_409_with_higher_priority_pending(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $openId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');
        $inProgressId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');

        $ticket = $this->createTicket($user, [
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now()->subHours(1),
        ]);

        $this->createTicket($user, [
            'priority' => TicketPriorityEnum::Critical->value,
            'status_id' => $openId,
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close-final", [
                'actual_cost' => 100.00,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('warning', true)
            ->assertJsonPath('can_force', true)
            ->assertJsonPath('urgent_tickets_count', 1);

        $ticket->refresh();
        $this->assertFalse($ticket->hasStatus(TicketStatusEnum::Closed));
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::InProgress));
    }

    public function test_close_final_with_force_closes_despite_higher_priority(): void
    {
        $this->createUserWithToken(UserRoleEnum::Admin->value);
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $openId = TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id');
        $inProgressId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');

        $ticket = $this->createTicket($user, [
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now()->subHours(1),
        ]);

        $this->createTicket($user, [
            'priority' => TicketPriorityEnum::Critical->value,
            'status_id' => $openId,
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close-final", [
                'actual_cost' => 100.00,
                'force' => true,
            ]);

        $response->assertOk();
        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Closed));

        $this->assertDatabaseHas('notifications', [
            'type' => NotificationTypeEnum::PriorityOverride->value,
        ]);
    }

    public function test_user_cannot_close_final(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $inProgressId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');
        $ticket = $this->createTicket($user, [
            'status_id' => $inProgressId,
            'in_progress_at' => now(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close-final", [
                'actual_cost' => 50.00,
            ]);

        $response->assertStatus(403);
    }

    public function test_close_final_validation_errors(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = TicketStatus::where('name', TicketStatusEnum::InProgress->value)->value('id');
        $ticket = $this->createTicket($user, [
            'status_id' => $inProgressId,
            'in_progress_at' => now(),
        ]);

        $send = fn (array $payload) => $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/close-final", $payload);

        // Test 1: missing actual_cost
        $send([])->assertStatus(422)->assertJsonValidationErrors(['actual_cost']);

        // Test 2: non-numeric actual_cost
        $send(['actual_cost' => 'abc'])->assertStatus(422)->assertJsonValidationErrors(['actual_cost']);

        // Test 3: negative actual_cost
        $send(['actual_cost' => -5])->assertStatus(422)->assertJsonValidationErrors(['actual_cost']);

        // Test 4: report longer than 5000 characters
        $send(['actual_cost' => 10, 'report' => str_repeat('a', 5001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['report']);

        // Test 5: non-boolean force
        $send(['actual_cost' => 10, 'force' => 'yes'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['force']);
    }

    public function test_start_rejects_non_boolean_force(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $ticket = $this->createTicket($user);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/start", [
                'force' => 'not-a-boolean',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['force']);
    }
}
