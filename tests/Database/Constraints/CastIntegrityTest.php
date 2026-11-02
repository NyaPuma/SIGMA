<?php

namespace Tests\Database\Constraints;

use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CastIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLookupData();
    }

    protected function seedLookupData(): void
    {
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Open->value], ['code' => 'ABERTA', 'description' => 'Aberta']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::InProgress->value], ['code' => 'EM_CURSO', 'description' => 'Em curso']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Closed->value], ['code' => 'FECHADA', 'description' => 'Fechada']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Cancelled->value], ['code' => 'CANCELADA', 'description' => 'Cancelada']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::PendingBudget->value], ['code' => 'PENDENTE_ORCAMENTO', 'description' => 'Pendente']);
        TicketStatus::firstOrCreate(['name' => TicketStatusEnum::Rejected->value], ['code' => 'RECUSADA', 'description' => 'Recusada']);
    }

    protected function createCommonUser(): User
    {
        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
        $token = 'user-persist-token-'.uniqid();
        $user = User::factory()->create([
            'profile_id' => $profile->id,
            'api_token' => $token,
        ]);
        $user->raw_token = $token;

        return $user;
    }

    protected function asUserWithToken(User $user): static
    {
        return $this->withHeader('X-Auth-Token', $user->api_token)
            ->withHeader('Accept', 'application/json');
    }

    // ==========================================
    // SECTION 10: DATA TYPE & CAST INTEGRITY
    // ==========================================

    public function test_ticket_datetime_casts(): void
    {
        $user = $this->createCommonUser();
        $this->asUserWithToken($user);

        $response = $this->postJson('/tickets', [
            'title' => 'Cast Test',
            'description' => 'DateTime casts',
            'priority' => 'baixa',
        ]);
        $ticketId = $response->json('ticket.id');

        $ticket = Ticket::find($ticketId);
        $this->assertNotNull($ticket->opened_at);
        $this->assertInstanceOf(Carbon::class, $ticket->opened_at);
    }

    public function test_ticket_json_cast_budget_details(): void
    {
        $user = $this->createCommonUser();
        $this->asUserWithToken($user);

        $details = [
            ['description' => 'Part A', 'type' => 'material', 'quantity' => 2, 'unit_price' => 15.50],
            ['description' => 'Labor', 'type' => 'labor', 'hours' => 3, 'hourly_rate' => 25.00],
        ];

        $response = $this->postJson('/tickets', [
            'title' => 'JSON Cast Test',
            'description' => 'JSON budget details',
            'priority' => 'alta',
        ]);
        $ticketId = $response->json('ticket.id');

        $ticket = Ticket::find($ticketId);
        $ticket->update(['budget_details' => $details]);

        $ticket->refresh();
        $this->assertIsArray($ticket->budget_details);
        $this->assertCount(2, $ticket->budget_details);
        $this->assertEquals('material', $ticket->budget_details[0]['type']);
    }

    public function test_ticket_boolean_cast_scheduled(): void
    {
        $user = $this->createCommonUser();
        $this->asUserWithToken($user);

        $response = $this->postJson('/tickets', [
            'title' => 'Boolean Cast Test',
            'description' => 'Boolean',
            'priority' => 'baixa',
        ]);
        $ticketId = $response->json('ticket.id');

        $ticket = Ticket::find($ticketId);
        $this->assertIsBool($ticket->scheduled);

        $ticket->update(['scheduled' => true]);
        $ticket->refresh();
        $this->assertTrue((bool) $ticket->scheduled);
    }

    public function test_user_boolean_cast_active(): void
    {
        $profile = UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
        $user = User::factory()->create([
            'profile_id' => $profile->id,
            'active' => true,
        ]);
        $this->assertIsBool($user->active);
        $this->assertTrue($user->active);
    }
}
