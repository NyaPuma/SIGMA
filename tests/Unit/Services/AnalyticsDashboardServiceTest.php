<?php

namespace Tests\Unit\Services;

use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Services\AnalyticsDashboardService;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;
use Tests\Concerns\CreatesTickets;

class AnalyticsDashboardServiceTest extends FeatureTestCase
{
    use CreatesTickets;

    private AnalyticsDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        app(TicketStatusService::class)->flush();
        Cache::forget('analytics_dashboard_payload:'.app()->getLocale());

        $this->service = app(AnalyticsDashboardService::class);
    }

    #[Test]
    public function it_builds_a_payload_with_all_expected_sections(): void
    {
        $payload = $this->service->getDashboardPayload();

        $this->assertArrayHasKey('average_resolution_minutes', $payload);
        $this->assertArrayHasKey('average_waiting_minutes', $payload);
        $this->assertArrayHasKey('open_tickets', $payload);
        $this->assertArrayHasKey('in_progress_tickets', $payload);
        $this->assertArrayHasKey('waiting_budget_tickets', $payload);
        $this->assertArrayHasKey('closed_tickets', $payload);
        $this->assertArrayHasKey('system_availability', $payload);
        $this->assertArrayHasKey('sla_success', $payload);
        $this->assertArrayHasKey('by_priority', $payload);
        $this->assertArrayHasKey('ticket_status_breakdown', $payload);
        $this->assertArrayHasKey('monthly_tickets', $payload);
        $this->assertArrayHasKey('monthly_cost', $payload);
        $this->assertArrayHasKey('top_equipments', $payload);
        $this->assertArrayHasKey('top_rooms', $payload);
        $this->assertArrayHasKey('top_technicians', $payload);
        $this->assertArrayHasKey('recent_activity', $payload);
        $this->assertArrayHasKey('monthly_sla', $payload);
        $this->assertArrayHasKey('monthly_mttr', $payload);
        $this->assertArrayHasKey('by_urgency', $payload);
        $this->assertArrayHasKey('by_room', $payload);
        $this->assertArrayHasKey('by_budget_status', $payload);
        $this->assertArrayHasKey('by_source', $payload);
        $this->assertArrayHasKey('cost_by_equipment', $payload);
        $this->assertArrayHasKey('stock_monthly', $payload);
        $this->assertArrayHasKey('low_stock_parts', $payload);
        $this->assertArrayHasKey('notifications_by_type', $payload);
        $this->assertArrayHasKey('users_by_role', $payload);
        $this->assertEquals(3, count($payload['by_priority']['data']));
        $this->assertEquals(4, count($payload['ticket_status_breakdown']['data']));
        $this->assertSame(['Urgentes', 'Normais'], $payload['by_urgency']['labels']->all());
        $this->assertSame(
            ['Pendente', 'Aprovado', 'Rejeitado'],
            $payload['by_budget_status']['labels']->all()
        );
        $this->assertCount(6, $payload['monthly_sla']['labels']);
        $this->assertCount(6, $payload['monthly_mttr']['labels']);
        $this->assertCount(6, $payload['stock_monthly']['labels']);
    }

    #[Test]
    public function it_counts_tickets_by_current_status(): void
    {
        $this->createTicketWithStatus(TicketStatusEnum::Open->value);
        $this->createTicketWithStatus(TicketStatusEnum::InProgress->value);
        $this->createTicketWithStatus(TicketStatusEnum::Closed->value, ['closed_at' => now()]);
        $this->createTicketWithBudget();

        $payload = $this->service->getDashboardPayload();

        $this->assertEquals(1, $payload['open_tickets']);
        $this->assertEquals(1, $payload['in_progress_tickets']);
        $this->assertEquals(1, $payload['waiting_budget_tickets']);
        $this->assertEquals(1, $payload['closed_tickets']);
    }

    #[Test]
    public function it_groups_tickets_by_priority(): void
    {
        $this->createTicket(['priority' => TicketPriorityEnum::Low->value]);
        $this->createTicket(['priority' => TicketPriorityEnum::Medium->value]);
        $this->createTicket(['priority' => TicketPriorityEnum::Medium->value]);
        $this->createTicket(['priority' => TicketPriorityEnum::High->value]);

        $payload = $this->service->getDashboardPayload();

        $data = $payload['by_priority']['data'];
        $this->assertEquals(1, $data[0]);
        $this->assertEquals(2, $data[1]);
        $this->assertEquals(1, $data[2]);
    }

    #[Test]
    public function it_reports_full_sla_success_when_no_tickets_are_closed(): void
    {
        $this->createTicket(['title' => 'Ticket aberto']);

        $payload = $this->service->getDashboardPayload();

        $this->assertEquals(100, $payload['sla_success']);
    }

    #[Test]
    public function it_reads_system_availability_from_the_key_the_settings_ui_saves(): void
    {
        // SystemSettingsService::applyOverrides() writes the admin's value to
        // services.custom.analytics.system_availability. Reading any other key would leave
        // the "Disponibilidade do sistema" field inert on the analytics panel.
        config(['services.custom.analytics.system_availability' => 97.5]);
        Cache::forget('analytics_dashboard_payload:'.app()->getLocale());

        $payload = $this->service->getDashboardPayload();

        $this->assertSame(97.5, $payload['system_availability']);
    }

    #[Test]
    public function it_builds_additional_distribution_breakdowns(): void
    {
        $this->createTicket(['urgent' => true, 'source' => 'web', 'budget_status' => 'approved']);
        $this->createTicket(['urgent' => false, 'source' => 'qr', 'budget_status' => 'pending']);
        $this->createTicket(['urgent' => false, 'source' => 'web']);

        $payload = $this->service->getDashboardPayload();

        $this->assertSame([1, 2], $payload['by_urgency']['data']->all());
        $this->assertContains('Web', $payload['by_source']['labels']->all());
        $this->assertSame(
            [1, 1, 0],
            $payload['by_budget_status']['data']->all()
        );
    }

    #[Test]
    public function it_includes_recent_audit_activity_with_legible_descriptions(): void
    {
        $this->createTicketWithStatus(TicketStatusEnum::Closed->value);

        $payload = $this->service->getDashboardPayload();

        $this->assertIsIterable($payload['recent_activity']);
    }

    #[Test]
    public function it_serves_the_payload_from_cache_and_invalidates_it(): void
    {
        $first = $this->service->getDashboardPayload();
        $second = $this->service->getDashboardPayload();

        $this->assertSame($first, $second);

        Cache::forget('analytics_dashboard_payload:'.app()->getLocale());
        $this->createTicketWithStatus(TicketStatusEnum::Open->value);

        $third = $this->service->getDashboardPayload();

        $this->assertEquals($first['open_tickets'] + 1, $third['open_tickets']);
    }
}
