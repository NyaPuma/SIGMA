<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\TicketStatusEnum;
use App\Models\Equipment;
use App\Models\Room;
use App\Services\TicketStatusService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;
use Tests\Concerns\CreatesTickets;

final class SimulateTelemetryTest extends FeatureTestCase
{
    use CreatesTickets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAdmin();
        app(TicketStatusService::class)->flush();
    }

    #[Test]
    public function it_skips_equipment_whose_ticket_is_still_being_worked_on(): void
    {
        $equipment = $this->createEquipment();
        $this->createTicketWithStatus(TicketStatusEnum::InProgress->value, ['equipment_id' => $equipment->id]);

        $this->artisan('telemetry:simulate', ['--equipments' => 5, '--probability' => 100])
            ->expectsOutputToContain('already has an active ticket')
            ->assertSuccessful();

        // A ticket that is only Open used to count as "active", so a second anomaly
        // ticket was created for equipment that was already being repaired.
        $this->assertSame(1, $equipment->tickets()->count());
    }

    #[Test]
    public function it_creates_a_ticket_for_equipment_whose_only_ticket_is_closed(): void
    {
        $equipment = $this->createEquipment();
        $this->createTicketWithStatus(TicketStatusEnum::Closed->value, ['equipment_id' => $equipment->id]);

        $this->artisan('telemetry:simulate', ['--equipments' => 5, '--probability' => 100])
            ->assertSuccessful();

        $this->assertSame(2, $equipment->tickets()->count());
        $this->assertDatabaseHas('tickets', [
            'equipment_id' => $equipment->id,
            'status_id' => app(TicketStatusService::class)->getByName(TicketStatusEnum::Open),
        ]);
    }

    #[Test]
    public function a_dry_run_reports_the_tickets_without_persisting_them(): void
    {
        $equipment = $this->createEquipment();

        $this->artisan('telemetry:simulate', ['--equipments' => 5, '--probability' => 100, '--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN] 1 maintenance ticket(s) generated')
            ->assertSuccessful();

        $this->assertSame(0, $equipment->tickets()->count());
    }

    private function createEquipment(): Equipment
    {
        return Equipment::factory()->create([
            'room_id' => Room::factory()->create()->id,
            'active' => true,
        ]);
    }
}
