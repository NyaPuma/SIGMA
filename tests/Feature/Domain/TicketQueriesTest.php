<?php

namespace Tests\Feature\Domain;

use App\Domain\Ticket\Queries\MonthlyTicketsQuery;
use App\Domain\Ticket\Queries\ScheduledEventsQuery;
use App\Domain\Ticket\Queries\TicketKpiQuery;
use App\Domain\Ticket\Queries\TicketPriorityQuery;
use App\Domain\Ticket\Queries\TopEntitiesQuery;
use App\Enums\BudgetStatusEnum;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketStatusService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\DatabaseTestCase;
use Tests\Concerns\CreatesTickets;

class TicketQueriesTest extends DatabaseTestCase
{
    use CreatesTickets;

    private TicketStatusService $statusService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusService = app(TicketStatusService::class);
    }

    #[Test]
    public function monthly_tickets_query_aggregates_last_six_months(): void
    {
        $now = Carbon::parse('2026-07-31 12:00:00');
        $open = $this->statusService->getByName(TicketStatusEnum::Open);
        $inProgress = $this->statusService->getByName(TicketStatusEnum::InProgress);
        $closed = $this->statusService->getByName(TicketStatusEnum::Closed);

        $this->createTicket(['status_id' => $open, 'opened_at' => '2026-07-10 09:00:00']);
        $this->createTicket(['status_id' => $open, 'opened_at' => '2026-07-15 09:00:00']);
        $this->createTicket(['status_id' => $inProgress, 'opened_at' => '2026-07-20 09:00:00']);
        $this->createTicket(['status_id' => $closed, 'opened_at' => '2026-07-05 09:00:00', 'closed_at' => '2026-07-06 09:00:00', 'actual_cost' => 100]);
        $this->createTicket(['status_id' => $closed, 'opened_at' => '2026-06-05 09:00:00', 'closed_at' => '2026-06-06 09:00:00', 'actual_cost' => 50]);
        $this->createTicket(['status_id' => $closed, 'opened_at' => '2025-01-05 09:00:00', 'closed_at' => '2025-01-06 09:00:00', 'actual_cost' => 999]);

        $result = (new MonthlyTicketsQuery($open, $inProgress, $closed, $now))->execute();

        $this->assertCount(6, $result['labels']);
        $this->assertEquals('2026-02', $result['labels'][0]);
        $this->assertEquals('2026-07', $result['labels'][5]);
        $this->assertEquals(2, $result['open'][5]);
        $this->assertEquals(1, $result['in_progress'][5]);
        $this->assertEquals(1, $result['closed'][5]);
        $this->assertEquals(100.0, $result['cost_data'][5]);
        $this->assertEquals(1, $result['closed'][4]);
        $this->assertEquals(50.0, $result['cost_data'][4]);
        $this->assertEquals(0, $result['open'][0]);
    }

    #[Test]
    public function scheduled_events_query_returns_events_within_range(): void
    {
        $this->createScheduledTicket();
        $ticket = $this->createScheduledTicket();

        $result = (new ScheduledEventsQuery(
            from: now()->toDateTimeString(),
            to: now()->addDays(2)->toDateTimeString(),
        ))->execute();

        $this->assertGreaterThanOrEqual(2, $result->count());
        $event = $result->firstWhere('id', $ticket->id);
        $this->assertNotNull($event);
        $this->assertEquals('#'.$ticket->id.' - Test Ticket', $event['title']);
        $this->assertNotNull($event['start']);
        $this->assertNotNull($event['end']);
    }

    #[Test]
    public function scheduled_events_query_respects_date_bounds(): void
    {
        $this->createScheduledTicket();

        $result = (new ScheduledEventsQuery(
            from: now()->addDays(10)->toDateTimeString(),
            to: now()->addDays(20)->toDateTimeString(),
        ))->execute();

        $this->assertCount(0, $result);
    }

    #[Test]
    public function ticket_kpi_query_returns_expected_metrics(): void
    {
        $open = $this->statusService->getByName(TicketStatusEnum::Open);
        $inProgress = $this->statusService->getByName(TicketStatusEnum::InProgress);
        $closed = $this->statusService->getByName(TicketStatusEnum::Closed);

        $this->createTicket(['status_id' => $open, 'opened_at' => now()->subHours(5)]);
        $this->createTicket(['status_id' => $open, 'opened_at' => now()->subHours(5)]);
        $this->createTicket(['status_id' => $inProgress, 'opened_at' => now()->subHours(3)]);
        $this->createTicket([
            'status_id' => $closed,
            'opened_at' => now()->subHours(4),
            'closed_at' => now()->subHours(3),
        ]);
        $this->createTicket([
            'status_id' => $open,
            'budget_requested' => true,
            'budget_status' => BudgetStatusEnum::Pending->value,
        ]);

        $result = (new TicketKpiQuery($open, $inProgress, $closed))->execute();

        $this->assertEquals(3, $result['open_tickets']);
        $this->assertEquals(1, $result['in_progress_tickets']);
        $this->assertEquals(1, $result['budget_pending_tickets']);
        $this->assertEquals(1, $result['closed_tickets']);
        $this->assertEqualsWithDelta(60.0, $result['avg_resolution'], 0.01);
        $this->assertEquals(1, $result['sla_met']);
        $this->assertIsFloat($result['avg_waiting']);
    }

    #[Test]
    public function ticket_kpi_query_measures_waiting_time_from_the_assignment_stamp(): void
    {
        $open = $this->statusService->getByName(TicketStatusEnum::Open);
        $technician = User::factory()->create();
        $openedAt = now()->subHours(9);

        $this->createTicket([
            'status_id' => $open,
            'opened_at' => $openedAt,
            'assigned_to' => $technician->id,
            'assigned_at' => $openedAt->copy()->addMinutes(120),
        ]);
        // Still queued: no assignment, so it must not drag the average towards zero.
        $this->createTicket(['status_id' => $open, 'opened_at' => $openedAt]);

        $result = (new TicketKpiQuery($open, $open, $open))->execute();

        $this->assertEqualsWithDelta(120.0, $result['avg_waiting'], 1.0);
    }

    #[Test]
    public function ticket_priority_query_counts_priorities(): void
    {
        $this->createTicketWithPriority(TicketPriorityEnum::Low->value);
        $this->createTicketWithPriority(TicketPriorityEnum::Low->value);
        $this->createTicketWithPriority(TicketPriorityEnum::Medium->value);
        $this->createTicketWithPriority(TicketPriorityEnum::Medium->value);
        $this->createTicketWithPriority(TicketPriorityEnum::Medium->value);
        $this->createTicketWithPriority(TicketPriorityEnum::High->value);
        $this->createTicketWithPriority(TicketPriorityEnum::Critical->value);

        $result = (new TicketPriorityQuery(Ticket::query()))->execute();

        $this->assertEquals(2, $result['low']);
        $this->assertEquals(3, $result['medium']);
        $this->assertEquals(2, $result['high']);
    }

    #[Test]
    public function top_entities_query_returns_top_equipments_rooms_and_technicians(): void
    {
        $technicianA = User::factory()->create(['name' => 'Técnica Ana']);
        $technicianB = User::factory()->create(['name' => 'Técnico Bruno']);

        $roomA = Room::factory()->create(['name' => 'Laboratório A']);
        $roomB = Room::factory()->create(['name' => 'Sala B']);

        $equipmentA = Equipment::factory()->create(['room_id' => $roomA->id, 'name' => 'Impressora A']);
        $equipmentB = Equipment::factory()->create(['room_id' => $roomB->id, 'name' => 'Portátil B']);

        $this->createTicket(['equipment_id' => $equipmentA->id, 'room_id' => $roomA->id, 'assigned_to' => $technicianA->id]);
        $this->createTicket(['equipment_id' => $equipmentA->id, 'room_id' => $roomA->id, 'assigned_to' => $technicianA->id]);
        $this->createTicket(['equipment_id' => $equipmentB->id, 'room_id' => $roomB->id, 'assigned_to' => $technicianB->id]);

        $baseQuery = Ticket::query();
        $query = new TopEntitiesQuery($baseQuery);

        $equipments = $query->getTopEquipments();
        $rooms = $query->getTopRooms();
        $technicians = $query->getTopTechnicians();

        $this->assertEquals('Impressora A', $equipments->first()['name']);
        $this->assertEquals(2, $equipments->first()['total']);
        $this->assertEquals('interventions', $equipments->first()['subtitle']);

        $this->assertEquals('Laboratório A', $rooms->first()['name']);
        $this->assertEquals(2, $rooms->first()['total']);
        $this->assertEquals('tickets', $rooms->first()['subtitle']);

        $this->assertEquals('Técnica Ana', $technicians->first()['name']);
        $this->assertEquals(2, $technicians->first()['total']);
        $this->assertEquals('actions', $technicians->first()['subtitle']);
    }
}
