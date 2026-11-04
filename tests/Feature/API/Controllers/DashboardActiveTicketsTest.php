<?php

namespace Tests\Feature\API\Controllers;

use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\TicketStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardActiveTicketsTest extends TestCase
{
    use RefreshDatabase;

    private ?User $adminUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        UserProfile::create(['name' => UserRoleEnum::User->value]);
        UserProfile::create(['name' => UserRoleEnum::Admin->value]);
        $this->artisan('db:seed', ['--class' => 'TicketLookupSeeder', '--force' => true]);
    }

    private function admin(): User
    {
        if ($this->adminUser === null) {
            $this->adminUser = User::factory()->create([
                'profile_id' => UserProfile::where('name', UserRoleEnum::Admin->value)->firstOrFail()->id,
                'api_token' => Str::random(60),
                'active' => true,
            ]);
        }

        return $this->adminUser;
    }

    private function createTicket(TicketStatusEnum $status, array $overrides = []): Ticket
    {
        $statusId = app(TicketStatusService::class)->getByName($status);

        return Ticket::create(array_merge([
            'title' => 'Ticket '.uniqid('t-', true),
            'description' => 'Descrição.',
            'priority' => 'média',
            'user_id' => $this->admin()->id,
            'status_id' => $statusId,
            'opened_at' => now(),
        ], $overrides));
    }

    #[Test]
    public function dashboard_tickets_active_returns_only_active_tickets(): void
    {
        $active = [
            $this->createTicket(TicketStatusEnum::Open),
            $this->createTicket(TicketStatusEnum::InProgress),
            $this->createTicket(TicketStatusEnum::PendingBudget),
        ];
        $this->createTicket(TicketStatusEnum::Closed);
        $this->createTicket(TicketStatusEnum::Cancelled);
        $this->createTicket(TicketStatusEnum::Rejected);

        $response = $this->withHeader('X-Auth-Token', $this->admin()->api_token)
            ->getJson('/dashboard/tickets-active');

        $response->assertOk()
            ->assertJsonStructure(['tickets']);

        $titles = collect($response->json('tickets'))->pluck('id')->all();
        $this->assertCount(3, $titles);

        foreach ($active as $ticket) {
            $this->assertContains($ticket->id, $titles);
        }
    }

    #[Test]
    public function dashboard_tickets_active_returns_empty_when_no_active_tickets(): void
    {
        $this->createTicket(TicketStatusEnum::Closed);

        $response = $this->withHeader('X-Auth-Token', $this->admin()->api_token)
            ->getJson('/dashboard/tickets-active');

        $response->assertOk();
        $this->assertEmpty($response->json('tickets'));
    }

    #[Test]
    public function dashboard_tickets_active_requires_authentication(): void
    {
        $this->getJson('/dashboard/tickets-active')->assertStatus(401);
    }

    #[Test]
    public function dashboard_tickets_active_hides_other_users_tickets_from_an_operator(): void
    {
        $operator = $this->createUserWithProfile(UserRoleEnum::User);
        $colleague = $this->createUserWithProfile(UserRoleEnum::User);

        $mine = $this->createTicket(TicketStatusEnum::Open, ['user_id' => $operator->id]);
        $theirs = $this->createTicket(TicketStatusEnum::Open, ['user_id' => $colleague->id]);

        $response = $this->withHeader('X-Auth-Token', $operator->api_token)
            ->getJson('/dashboard/tickets-active');

        $response->assertOk();

        $ids = collect($response->json('tickets'))->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    #[Test]
    public function dashboard_tickets_active_limits_a_technician_to_assigned_tickets(): void
    {
        $technician = $this->createUserWithProfile(UserRoleEnum::Technician);
        $otherTechnician = $this->createUserWithProfile(UserRoleEnum::Technician);

        $assigned = $this->createTicket(TicketStatusEnum::InProgress, ['assigned_to' => $technician->id]);
        $unassigned = $this->createTicket(TicketStatusEnum::InProgress, ['assigned_to' => $otherTechnician->id]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/dashboard/tickets-active');

        $response->assertOk();

        $ids = collect($response->json('tickets'))->pluck('id')->all();

        $this->assertContains($assigned->id, $ids);
        $this->assertNotContains($unassigned->id, $ids);
    }

    private function createUserWithProfile(UserRoleEnum $role): User
    {
        $profile = UserProfile::firstOrCreate(['name' => $role->value]);

        return User::factory()->create([
            'profile_id' => $profile->id,
            'api_token' => Str::random(60),
            'active' => true,
        ]);
    }
}
