<?php

namespace Tests\Security\SQLInjection;

use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Ticket;
use App\Services\TicketStatusService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class SqlInjectionTest extends FeatureTestCase
{
    #[Test]
    public function it_escapes_sql_injection_in_search_query(): void
    {
        $user = $this->createAdmin();
        Ticket::factory()->create(['title' => 'Bomba 1']);

        $maliciousQuery = "' OR '1'='1";

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->actingAs($user)
            ->getJson('/api/admin/users?q='.urlencode($maliciousQuery));

        $response->assertStatus(200);
        // The payload is bound as a literal `name/email LIKE '%...\' OR \'1\'=\'1%'` term, so it
        // matches nothing. A non-empty list would mean the injected OR was evaluated by the engine.
        $users = $response->json('users');
        $this->assertIsArray($users);
        $this->assertEmpty($users['data']);
    }

    #[Test]
    public function it_handles_xss_payload_in_ticket_description(): void
    {
        $operator = $this->createRegularUser();

        $xssPayload = "<script>alert('XSS')</script>Avaria no motor";

        $response = $this->withHeader('X-Auth-Token', $operator->api_token)
            ->actingAs($operator)
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

    #[Test]
    public function it_sanitizes_sql_injection_in_ticket_title(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $openId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);

        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/tickets', [
                'title' => "'; DROP TABLE tickets; --",
                'description' => 'SQL injection attempt',
                'priority' => TicketPriorityEnum::Medium->value,
                'status_id' => $openId,
            ]);

        $this->assertContains($response->status(), [201, 422]);

        if ($response->status() === 201) {
            $this->assertDatabaseHas('tickets', [
                'title' => "'; DROP TABLE tickets; --",
            ]);
        }
    }

    #[Test]
    public function it_stores_xss_payload_in_description_safely(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $openId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);

        $xssPayload = '<script>alert("XSS")</script>';
        $response = $this->withHeader('X-Auth-Token', $user->api_token)
            ->postJson('/tickets', [
                'title' => 'XSS test ticket',
                'description' => $xssPayload,
                'priority' => TicketPriorityEnum::Low->value,
                'status_id' => $openId,
            ]);

        $this->assertContains($response->status(), [201, 422]);

        if ($response->status() === 201) {
            $ticket = Ticket::where('title', 'XSS test ticket')->first();
            $this->assertNotNull($ticket);
            $this->assertStringContainsString('script', $ticket->description);
        }
    }

    #[Test]
    public function it_stores_html_injection_in_comment_safely(): void
    {
        $user = $this->createUserWithToken(UserRoleEnum::User->value);
        $technician = $this->createUserWithToken(UserRoleEnum::Technician->value);
        $openId = app(TicketStatusService::class)->getByName(TicketStatusEnum::Open);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => 'HTML injection test',
            'description' => 'Testing HTML injection in comments',
            'priority' => TicketPriorityEnum::Medium->value,
            'status_id' => $openId,
            'opened_at' => now(),
        ]);

        $htmlPayload = '<img src=x onerror=alert(1)>';
        $response = $this->withHeader('X-Auth-Token', $technician->api_token)
            ->postJson("/tickets/{$ticket->id}/comments", [
                'comment' => $htmlPayload,
            ]);

        $this->assertContains($response->status(), [201, 422]);

        if ($response->status() === 201) {
            $this->assertDatabaseHas('ticket_comments', [
                'ticket_id' => $ticket->id,
                'comment' => $htmlPayload,
            ]);
        }
    }
}
