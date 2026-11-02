<?php

namespace Tests\Feature\API\Controllers;

use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Tests\Base\FeatureTestCase;

class AnalyticsFeatureTest extends FeatureTestCase
{
    public function test_operator_is_forbidden_from_analytics_endpoints()
    {
        $operator = $this->createRegularUser();

        $this->withHeader('X-Auth-Token', $operator->api_token)
            ->actingAs($operator)
            ->getJson('/api/analytics/stats')
            ->assertStatus(403);
    }

    public function test_admin_can_access_analytics_stats()
    {
        $statusOpen = TicketStatus::where('name', TicketStatusEnum::Open->value)->first();
        $statusClosed = TicketStatus::where('name', TicketStatusEnum::Closed->value)->first();

        Ticket::factory()->create([
            'status_id' => $statusOpen->id,
            'opened_at' => now()->subHours(2),
        ]);

        Ticket::factory()->create([
            'status_id' => $statusClosed->id,
            'opened_at' => now()->subHours(5),
            'closed_at' => now()->subHours(1),
            'estimated_cost' => 150.00,
        ]);

        $admin = $this->createAdmin();

        $response = $this->withHeader('X-Auth-Token', $admin->api_token)
            ->getJson('/api/analytics/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'average_resolution_minutes',
                'average_waiting_minutes',
                'open_tickets',
                'closed_tickets',
                'system_availability',
                'sla_success',
                'by_priority',
                'ticket_status_breakdown',
            ]);
    }

    public function test_operator_is_forbidden_from_export_endpoints()
    {
        $operator = $this->createRegularUser();

        foreach (['csv', 'pdf', 'excel'] as $format) {
            $this->withHeader('X-Auth-Token', $operator->api_token)
                ->getJson("/api/analytics/export/{$format}")
                ->assertStatus(403);
        }
    }

    public function test_technician_is_blocked_from_analytics_endpoints()
    {
        $technician = $this->createTechnician();

        $this->withHeader('X-Auth-Token', $technician->api_token)
            ->getJson('/api/analytics/stats')
            ->assertStatus(403);

        foreach (['csv', 'pdf', 'excel'] as $format) {
            $this->withHeader('X-Auth-Token', $technician->api_token)
                ->getJson("/api/analytics/export/{$format}")
                ->assertStatus(403);
        }
    }

    public function test_export_csv_dispatches_processing_message()
    {
        $admin = $this->createAdmin();
        Ticket::factory()->create();

        $response = $this->withHeader('X-Auth-Token', $admin->api_token)
            ->get('/api/analytics/export/csv');

        $response->assertOk();
        $response->assertJsonPath('message', 'Exportação CSV em processamento. Receberá uma notificação quando estiver pronta.');
    }

    public function test_export_pdf_dispatches_processing_message()
    {
        $admin = $this->createAdmin();

        $response = $this->withHeader('X-Auth-Token', $admin->api_token)
            ->get('/api/analytics/export/pdf');

        $response->assertOk();
        $response->assertJsonPath('message', 'Exportação PDF em processamento. Receberá uma notificação quando estiver pronta.');
    }

    public function test_export_excel_dispatches_processing_message()
    {
        $admin = $this->createAdmin();

        $response = $this->withHeader('X-Auth-Token', $admin->api_token)
            ->get('/api/analytics/export/excel');

        $response->assertOk();
        $response->assertJsonPath('message', 'Exportação Excel em processamento. Receberá uma notificação quando estiver pronta.');
    }
}
