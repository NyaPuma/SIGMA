<?php

namespace Tests\Feature\API\Controllers;

use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        UserProfile::create(['name' => UserRoleEnum::Technician->value]);
        UserProfile::create(['name' => UserRoleEnum::User->value]);

        $this->artisan('db:seed', ['--class' => 'TicketLookupSeeder', '--force' => true]);
    }

    public function test_ticket_search_filters_by_keyword_priority_and_date_range(): void
    {
        $technicianProfile = UserProfile::where('name', UserRoleEnum::Technician->value)->first();

        $technician = User::factory()->create([
            'profile_id' => $technicianProfile->id,
            'api_token' => Str::random(60),
        ]);

        Ticket::create([
            'user_id' => $technician->id,
            'assigned_to' => $technician->id,
            'title' => 'Motor compressor overheating',
            'description' => 'Issue on the main compressor motor.',
            'priority' => TicketPriorityEnum::High->value,
            'status_id' => TicketStatus::where('name', TicketStatusEnum::Open->value)->value('id'),
            'opened_at' => now()->subDays(2),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/api/tickets/search?q=compressor&priority='.TicketPriorityEnum::High->value.'&date_from='.now()->subDays(7)->toDateString());

        $response->assertOk();
        $response->assertJsonStructure(['tickets']);
        $tickets = $response->json('tickets')['data'] ?? $response->json('tickets');
        $this->assertCount(1, $tickets);
        $this->assertStringContainsString('compressor', strtolower($tickets[0]['title']));
    }

    public function test_ticket_search_returns_empty_results_when_no_match(): void
    {
        $technicianProfile = UserProfile::where('name', UserRoleEnum::Technician->value)->first();

        $technician = User::factory()->create([
            'profile_id' => $technicianProfile->id,
            'api_token' => Str::random(60),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/api/tickets/search?q=this-should-not-match-anything');

        $response->assertOk();
        $response->assertJsonStructure(['tickets']);
        $tickets = $response->json('tickets')['data'] ?? $response->json('tickets');
        $this->assertEmpty($tickets);
    }

    public function test_ticket_search_rejects_invalid_date_range(): void
    {
        $technicianProfile = UserProfile::where('name', UserRoleEnum::Technician->value)->first();

        $technician = User::factory()->create([
            'profile_id' => $technicianProfile->id,
            'api_token' => Str::random(60),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/api/tickets/search?date_from='.now()->toDateString().'&date_to='.now()->subDays(1)->toDateString());

        $response->assertStatus(422);
        $response->assertJson(['message' => 'dateFrom cannot be later than dateTo.']);
    }

    public function test_ticket_search_validates_priority_enum(): void
    {
        $technicianProfile = UserProfile::where('name', UserRoleEnum::Technician->value)->first();

        $technician = User::factory()->create([
            'profile_id' => $technicianProfile->id,
            'api_token' => Str::random(60),
        ]);

        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/api/tickets/search?priority=invalid-priority');

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Prioridade inválida. Valores válidos: baixa, média, alta, crítica.']);
    }
}