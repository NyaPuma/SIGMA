<?php

namespace Tests\Feature\API\Controllers;

use App\Enums\BudgetStatusEnum;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\TicketStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BudgetFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        UserProfile::create(['name' => UserRoleEnum::User->value]);
        UserProfile::create(['name' => UserRoleEnum::Technician->value]);
        UserProfile::create(['name' => UserRoleEnum::Admin->value]);
        $this->artisan('db:seed', ['--class' => 'TicketLookupSeeder', '--force' => true]);
    }

    private function createUserWithToken(string $profileName): User
    {
        $profile = UserProfile::where('name', $profileName)->firstOrFail();

        return User::factory()->create([
            'profile_id' => $profile->id,
            'api_token' => Str::random(60),
            'active' => true,
        ]);
    }

    public function test_technician_can_request_budget_authorization(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Budget test ticket',
            'description' => 'Needs external parts',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now(),
            'opened_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/request-budget", [
                'budget_amount' => 500.00,
                'budget_justification' => 'Need to replace the main motor bearing - special order part',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['ticket' => [
                'id',
                'status_id',
                'budget_requested',
                'budget_status',
                'budget_amount',
                'budget_requested_at',
            ]]);

        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::PendingBudget));
        $this->assertTrue($ticket->budget_requested);
        $this->assertEquals(BudgetStatusEnum::Pending->value, $ticket->budget_status);
        $this->assertEquals(500.00, (float) $ticket->budget_amount);
        $this->assertNotNull($ticket->budget_requested_at);
    }

    public function test_non_technician_cannot_request_budget(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Budget test ticket',
            'description' => 'Needs parts',
            'priority' => TicketPriorityEnum::Low->value,
            'status_id' => $inProgressId,
            'opened_at' => now(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/request-budget", [
                'budget_amount' => 100.00,
                'budget_justification' => 'Test',
            ]);

        $response->assertStatus(403);
    }

    public function test_budget_request_validates_required_fields(): void
    {
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);
        $user = $this->createUserWithToken(UserRoleEnum::User->value);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Budget validation ticket',
            'description' => 'Testing validation',
            'priority' => TicketPriorityEnum::High->value,
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now(),
            'opened_at' => now(),
        ]);

        // Missing both required fields
        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/request-budget", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget_amount']);
    }

    public function test_admin_can_approve_budget(): void
    {
        $admin = $this->createUserWithToken(UserRoleEnum::Admin->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $pendingId = app(TicketStatusService::class)->getByName(TicketStatusEnum::PendingBudget);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Budget approval ticket',
            'description' => 'Testing budget approval',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $pendingId,
            'assigned_to' => $technician->id,
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
            'budget_amount' => 750.00,
            'budget_requested_at' => now()->subDay(),
            'opened_at' => now()->subDays(2),
        ]);

        $response = $this->withHeader('X-Auth-Token', $admin->api_token)
            ->patchJson("/api/admin/tickets/{$ticket->id}/approve-budget", [
                'decision' => 'approve',
            ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Orçamento aprovado. Ticket desbloqueado para intervenção.');
        $ticket->refresh();
        $this->assertEquals(BudgetStatusEnum::Approved->value, $ticket->budget_status);
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::InProgress));
        $this->assertEquals($admin->id, $ticket->budget_approved_by);
        $this->assertNotNull($ticket->budget_decided_at);
    }

    public function test_admin_can_reject_budget(): void
    {
        $admin = $this->createUserWithToken(UserRoleEnum::Admin->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $pendingId = app(TicketStatusService::class)->getByName(TicketStatusEnum::PendingBudget);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Budget rejection ticket',
            'description' => 'Testing budget rejection',
            'priority' => TicketPriorityEnum::Low->value,
            'status_id' => $pendingId,
            'assigned_to' => $technician->id,
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
            'budget_amount' => 999.99,
            'budget_requested_at' => now()->subDay(),
            'opened_at' => now()->subDays(2),
        ]);

        $response = $this->withHeader('X-Auth-Token', $admin->api_token)
            ->patchJson("/api/admin/tickets/{$ticket->id}/approve-budget", [
                'decision' => 'reject',
                'feedback' => 'Budget too high for this type of repair',
            ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Orçamento recusado. Reparação abortada.');
        $ticket->refresh();
        $this->assertEquals(BudgetStatusEnum::Rejected->value, $ticket->budget_status);
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::Rejected));
        $this->assertEquals($admin->id, $ticket->budget_approved_by);
    }

    public function test_non_admin_cannot_approve_budget(): void
    {
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $pendingId = app(TicketStatusService::class)->getByName(TicketStatusEnum::PendingBudget);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Budget unauthorized ticket',
            'description' => 'Testing unauthorized budget decision',
            'priority' => TicketPriorityEnum::High->value,
            'status_id' => $pendingId,
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
            'budget_amount' => 200.00,
            'budget_requested_at' => now(),
            'opened_at' => now(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->patchJson("/api/admin/tickets/{$ticket->id}/approve-budget", [
                'decision' => 'approve',
            ]);

        $response->assertStatus(403);
    }

    public function test_unassigned_technician_cannot_request_budget_authorization(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Unassigned technician budget',
            'description' => 'Only the assigned technician may request budget authorization',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $inProgressId,
            'in_progress_at' => now(),
            'opened_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/request-budget", [
                'budget_amount' => 500.00,
                'budget_justification' => 'Parts needed',
            ]);

        $response->assertStatus(403);

        $ticket->refresh();
        $this->assertNull($ticket->assigned_to);
        $this->assertFalse($ticket->budget_requested);
    }

    public function test_request_budget_authorization_rejected_while_budget_pending(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $pendingId = app(TicketStatusService::class)->getByName(TicketStatusEnum::PendingBudget);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Duplicate pending budget',
            'description' => 'A second request must be rejected while one is pending',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $pendingId,
            'assigned_to' => $technician->id,
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
            'budget_amount' => 700.00,
            'budget_requested_at' => now()->subHour(),
            'opened_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/request-budget", [
                'budget_amount' => 900.00,
                'budget_justification' => 'Another estimate',
            ]);

        $response->assertStatus(422);

        $ticket->refresh();
        $this->assertEquals(700.00, (float) $ticket->budget_amount);
        $this->assertEquals(BudgetStatusEnum::Pending->value, $ticket->budget_status);
    }

    public function test_request_budget_authorization_rejected_for_closed_ticket(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $closedId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Closed);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Closed ticket budget',
            'description' => 'No budget may be requested for a closed ticket',
            'priority' => TicketPriorityEnum::Low->value,
            'status_id' => $closedId,
            'assigned_to' => $technician->id,
            'opened_at' => now()->subDays(2),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/request-budget", [
                'budget_amount' => 100.00,
                'budget_justification' => 'Late charge',
            ]);

        $response->assertStatus(422);
    }

    public function test_technician_can_submit_estimate_above_threshold(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Estimate above threshold',
            'description' => 'Submitting a high estimate',
            'priority' => TicketPriorityEnum::High->value,
            'status_id' => $inProgressId,
            'in_progress_at' => now(),
            'opened_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->postJson('/tickets/'.$ticket->id.'/budget', [
                'estimated_budget' => 500.00,
            ]);

        $response->assertOk();
        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::PendingBudget));
        $this->assertTrue($ticket->budget_requested);
        $this->assertEquals(BudgetStatusEnum::Pending->value, $ticket->budget_status);
        $this->assertEquals(500.00, (float) $ticket->budget_amount);
        $this->assertNotNull($ticket->budget_requested_at);
        $this->assertEquals($technician->id, $ticket->assigned_to);
    }

    public function test_technician_cannot_submit_estimate_for_ticket_assigned_to_another_technician(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $owner = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $other = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Estimate owned by another technician',
            'description' => 'A colleague should not be able to quote it.',
            'priority' => TicketPriorityEnum::High->value,
            'status_id' => $inProgressId,
            'assigned_to' => $owner->id,
            'in_progress_at' => now(),
            'opened_at' => now()->subDay(),
        ]);

        $this->withHeader('X-Auth-Token', $other->api_token)
            ->postJson('/tickets/'.$ticket->id.'/budget', ['estimated_budget' => 500.00])
            ->assertStatus(403);

        $ticket->refresh();
        $this->assertEquals($owner->id, $ticket->assigned_to);
        $this->assertFalse((bool) $ticket->budget_requested);
    }

    public function test_technician_can_submit_estimate_below_threshold_auto_approved(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Estimate below threshold',
            'description' => 'Submitting a low estimate',
            'priority' => TicketPriorityEnum::Low->value,
            'status_id' => $inProgressId,
            'in_progress_at' => now(),
            'opened_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->postJson('/tickets/'.$ticket->id.'/budget', [
                'estimated_budget' => 30.00,
            ]);

        $response->assertOk();
        $ticket->refresh();
        $this->assertTrue($ticket->hasStatus(TicketStatusEnum::InProgress));
        $this->assertTrue($ticket->budget_requested);
        $this->assertNull($ticket->budget_status);
        $this->assertEquals(30.00, (float) $ticket->budget_amount);
    }

    public function test_user_cannot_submit_estimate(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $openId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'User estimate blocked',
            'description' => 'Common users cannot submit budget estimates',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $openId,
            'opened_at' => now(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/tickets/'.$ticket->id.'/budget', [
                'estimated_budget' => 100.00,
            ]);

        $response->assertStatus(403);
    }

    public function test_submit_estimate_rejected_while_budget_pending(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $pendingId = app(TicketStatusService::class)->getByName(TicketStatusEnum::PendingBudget);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Estimate while pending',
            'description' => 'No estimate allowed while a budget request is pending',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $pendingId,
            'assigned_to' => $technician->id,
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
            'budget_amount' => 300.00,
            'budget_requested_at' => now()->subHour(),
            'opened_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->postJson('/tickets/'.$ticket->id.'/budget', [
                'estimated_budget' => 150.00,
            ]);

        $response->assertStatus(422);

        $ticket->refresh();
        $this->assertEquals(300.00, (float) $ticket->budget_amount);
    }

    public function test_approve_budget_validation_errors(): void
    {
        $admin = $this->createUserWithToken(UserRoleEnum::Admin->value);
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $pendingId = app(TicketStatusService::class)->getByName(TicketStatusEnum::PendingBudget);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Budget validation ticket',
            'description' => 'Testing budget validation errors',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $pendingId,
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
            'budget_amount' => 100.00,
            'budget_requested_at' => now(),
            'opened_at' => now(),
        ]);

        $send = fn (array $payload) => $this->withHeader('X-Auth-Token', $admin->api_token)
            ->patchJson("/api/admin/tickets/{$ticket->id}/approve-budget", $payload);

        // Test 1: missing decision
        $send([])->assertStatus(422)->assertJsonValidationErrors(['decision']);

        // Test 2: invalid decision
        $send(['decision' => 'maybe'])->assertStatus(422)->assertJsonValidationErrors(['decision']);

        // Test 3: rejection without feedback
        $send(['decision' => 'reject'])->assertStatus(422)->assertJsonValidationErrors(['feedback']);

        // Test 4: feedback above 5000 characters
        $send(['decision' => 'approve', 'feedback' => str_repeat('a', 5001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['feedback']);
    }

    public function test_request_budget_validates_line_items_and_total(): void
    {
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);
        $user = $this->createUserWithToken(UserRoleEnum::User->value);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Budget line items ticket',
            'description' => 'Testing detailed budget line items',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now(),
            'opened_at' => now(),
        ]);

        $send = fn (array $payload) => $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/request-budget", $payload);

        // Test 1: sum of items does not match budget_amount
        $send([
            'budget_amount' => 100.00,
            'budget_details' => [
                ['description' => 'Peça A', 'quantity' => 2, 'unit_price' => 10.00],
                ['description' => 'Peça B', 'quantity' => 1, 'unit_price' => 20.00],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['budget_amount']);

        // Test 2: item without description
        $send([
            'budget_amount' => 40.00,
            'budget_details' => [
                ['quantity' => 2, 'unit_price' => 20.00],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['budget_details.0.description']);

        // Test 3: invalid quantity
        $send([
            'budget_amount' => 40.00,
            'budget_details' => [
                ['description' => 'Peça A', 'quantity' => 0, 'unit_price' => 20.00],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['budget_details.0.quantity']);

        // Test 4: negative unit price
        $send([
            'budget_amount' => 40.00,
            'budget_details' => [
                ['description' => 'Peça A', 'quantity' => 2, 'unit_price' => -10.00],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['budget_details.0.unit_price']);

        // Test 5: correct sum is accepted
        $send([
            'budget_amount' => 40.00,
            'budget_details' => [
                ['description' => 'Peça A', 'quantity' => 2, 'unit_price' => 20.00],
            ],
        ])->assertOk();
    }

    public function test_submit_estimate_validates_material_and_labor_items(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Submit estimate line items',
            'description' => 'Testing material and labor line items',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $inProgressId,
            'in_progress_at' => now(),
            'opened_at' => now(),
        ]);

        $send = fn (array $payload) => $this->withHeader('X-Auth-Token', $technician->api_token)
            ->postJson('/tickets/'.$ticket->id.'/budget', $payload);

        // Test 1: material item without quantity
        $send([
            'estimated_budget' => 40.00,
            'budget_details' => [
                ['description' => 'Peça A', 'type' => 'material', 'unit_price' => 20.00],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['budget_details.0.quantity']);

        // Test 2: labor item without hours
        $send([
            'estimated_budget' => 40.00,
            'budget_details' => [
                ['description' => 'Mão de obra', 'type' => 'labor', 'hourly_rate' => 20.00],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['budget_details.0.hours']);

        // Test 3: invalid type
        $send([
            'estimated_budget' => 40.00,
            'budget_details' => [
                ['description' => 'Peça A', 'type' => 'fuel', 'quantity' => 2, 'unit_price' => 20.00],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['budget_details.0.type']);

        // Test 4: sum does not match the total
        $send([
            'estimated_budget' => 100.00,
            'budget_details' => [
                ['description' => 'Peça A', 'type' => 'material', 'quantity' => 2, 'unit_price' => 10.00],
                ['description' => 'Mão de obra', 'type' => 'labor', 'hours' => 2, 'hourly_rate' => 25.00],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['estimated_budget']);

        // Test 5: correct sum (material + labor) is accepted
        $send([
            'estimated_budget' => 70.00,
            'budget_details' => [
                ['description' => 'Peça A', 'type' => 'material', 'quantity' => 2, 'unit_price' => 10.00],
                ['description' => 'Mão de obra', 'type' => 'labor', 'hours' => 2, 'hourly_rate' => 25.00],
            ],
        ])->assertOk();
    }

    public function test_new_budget_request_replaces_the_previous_decision(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $admin = $this->createUserWithToken(UserRoleEnum::Admin->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Resubmission after approval',
            'description' => 'A new estimate must not inherit the old decision',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now(),
            'opened_at' => now()->subDays(3),
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Approved->value,
            'budget_amount' => 500.00,
            'budget_requested_at' => now()->subDays(2),
            'budget_approved_by' => $admin->id,
            'budget_decided_at' => now()->subDay(),
        ]);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->putJson("/api/technician/tickets/{$ticket->id}/request-budget", [
                'budget_amount' => 800.00,
                'budget_justification' => 'The part price went up since the approval',
            ])
            ->assertOk();

        $ticket->refresh();

        $this->assertEquals(BudgetStatusEnum::Pending->value, $ticket->budget_status);
        // The approval belongs to a request this one replaced; keeping it would attribute the new
        // pending request to that admin and skew the budget-pause measurement.
        $this->assertNull($ticket->budget_approved_by);
        $this->assertNull($ticket->budget_decided_at);
    }

    public function test_submit_estimate_stores_line_items_as_one_json_encoding(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $inProgressId = app(TicketStatusService::class)->getByName(TicketStatusEnum::InProgress);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'Line item encoding',
            'description' => 'The detail column must read back as a list',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $inProgressId,
            'assigned_to' => $technician->id,
            'in_progress_at' => now(),
            'opened_at' => now(),
        ]);

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->postJson('/tickets/'.$ticket->id.'/budget', [
                'estimated_budget' => 70.00,
                'budget_details' => [
                    ['description' => 'Peça A', 'type' => 'material', 'quantity' => 2, 'unit_price' => 10.00],
                    ['description' => 'Mão de obra', 'type' => 'labor', 'hours' => 2, 'hourly_rate' => 25.00],
                ],
            ])->assertOk();

        $stored = Ticket::find($ticket->id)->getRawOriginal('budget_details');

        // One decode has to yield the item list. A double encoding (json_encode before assigning
        // to an array-cast column) leaves readers with a string where they expect an array.
        $this->assertIsArray(json_decode((string) $stored, true));
        $this->assertCount(2, Ticket::find($ticket->id)->budget_details);
    }
}
