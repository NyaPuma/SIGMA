<?php

namespace Tests\Security\SQLInjection;

use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityVulnerabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'TicketLookupSeeder', '--force' => true]);
    }

    public function test_sql_injection_attempt_in_search_query_is_safely_escaped()
    {
        $adminProfile = UserProfile::firstOrCreate(['name' => UserRoleEnum::Admin->value]);
        $user = User::factory()->create([
            'profile_id' => $adminProfile->id,
            'api_token' => Str::random(60),
        ]);
        Ticket::factory()->create(['title' => 'Bomba 1']);

        $maliciousQuery = "' OR '1'='1";

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->getJson('/api/admin/users?q='.urlencode($maliciousQuery));

        $response->assertStatus(200);
        // The payload must be bound as a literal search term: matching it against name/email
        // returns nobody. Returning the user list here would mean the quote closed the string.
        $users = $response->json('users');
        $this->assertIsArray($users);
        $this->assertEmpty($users['data']);
        $this->assertNotContains($user->email, array_column($users['data'], 'email'));
    }

    public function test_xss_payload_in_ticket_description_does_not_execute_raw_script()
    {
        $userProfile = UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
        /** @var User $operator */
        $operator = User::factory()->create([
            'profile_id' => $userProfile->id,
            'api_token' => Str::random(60),
        ]);
        $this->assertInstanceOf(User::class, $operator);

        $authenticatedOperator = $operator instanceof User ? $operator : User::findOrFail($operator->id);

        $xssPayload = "<script>alert('XSS')</script>Avaria no motor";

        $response = $this->actingAs($authenticatedOperator)
            ->withHeader('X-Auth-Token', $operator->api_token)
            ->postJson('/api/tickets', [
                'title' => 'Avaria XSS Test',
                'description' => $xssPayload,
                'priority' => 'média',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tickets', [
            'title' => 'Avaria XSS Test',
        ]);
    }

    public function test_broken_access_control_operator_cannot_create_users()
    {
        $userProfile = UserProfile::firstOrCreate(['name' => UserRoleEnum::User->value]);
        $operator = User::factory()->create([
            'profile_id' => $userProfile->id,
            'api_token' => Str::random(60),
        ]);

        // Operator attempts to create a new user via admin endpoint
        $authenticatedOperator = $operator->fresh();
        $this->assertInstanceOf(User::class, $authenticatedOperator);

        $response = $this->actingAs($authenticatedOperator)
            ->withHeader('X-Auth-Token', $operator->api_token)
            ->postJson('/api/admin/users', [
                'name' => 'Hacker Account',
                'email' => 'hacker@empresa.pt',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => UserRoleEnum::Admin->value,
                'profile_id' => $userProfile->id,
            ]);

        $response->assertStatus(403);
    }
}
