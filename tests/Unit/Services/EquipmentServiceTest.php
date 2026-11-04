<?php

namespace Tests\Unit\Services;

use App\Models\Equipment;
use App\Models\Room;
use App\Services\EquipmentService;
use App\Services\TicketStatusService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class EquipmentServiceTest extends FeatureTestCase
{
    private EquipmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        app(TicketStatusService::class)->flush();

        $this->service = new EquipmentService;
    }

    #[Test]
    public function it_lists_equipment_paginated_by_fifteen_per_page(): void
    {
        Equipment::factory()->count(20)->create(['name' => 'Impressora HP']);

        $result = $this->service->listPaginated();

        $this->assertEquals(15, $result->perPage());
        $this->assertEquals(20, $result->total());
        $this->assertCount(15, $result->items());
    }

    #[Test]
    public function it_filters_equipment_by_name(): void
    {
        Equipment::factory()->create(['name' => 'Impressora Laser']);
        Equipment::factory()->create(['name' => 'Portátil']);
        Equipment::factory()->create(['name' => 'Servidor']);

        $result = $this->service->listPaginated('impress');

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Impressora Laser', $result->items()[0]->name);
    }

    #[Test]
    public function it_filters_equipment_by_serial(): void
    {
        Equipment::factory()->create(['name' => 'Item A', 'serial' => 'LAP-2026']);
        Equipment::factory()->create(['name' => 'Item B', 'serial' => 'SRV-2026']);

        $result = $this->service->listPaginated('LAP-');

        $this->assertEquals(1, $result->total());
        $this->assertEquals('LAP-2026', $result->items()[0]->serial);
    }

    #[Test]
    public function it_does_not_return_false_positives_when_searching_wildcard_characters(): void
    {
        Equipment::factory()->create(['name' => 'Câmara AB_CD', 'serial' => 'CAM-1']);
        Equipment::factory()->create(['name' => 'Câmara ABXCD', 'serial' => 'CAM-2']);

        $result = $this->service->listPaginated('AB_CD');

        $this->assertCount(1, $result->items());
        $this->assertEquals('Câmara AB_CD', $result->items()[0]->name);
    }

    #[Test]
    public function it_matches_a_literal_percent_when_searching(): void
    {
        Equipment::factory()->create(['name' => 'Álcool 70% iso', 'serial' => 'ALC-1']);
        Equipment::factory()->create(['name' => 'Impressora 3D', 'serial' => 'PRI-1']);

        $result = $this->service->listPaginated('70%');

        $this->assertCount(1, $result->items());
        $this->assertEquals('Álcool 70% iso', $result->items()[0]->name);
    }

    #[Test]
    public function it_filters_by_active_status(): void
    {
        Equipment::factory()->create(['name' => 'Operacional', 'status' => 'operacional']);
        Equipment::factory()->create(['name' => 'Em Manutenção', 'status' => 'manutenção']);
        Equipment::factory()->create(['name' => 'Avariado', 'status' => 'avariado']);

        $operational = $this->service->listPaginated(null, 'operacional');
        $maintenance = $this->service->listPaginated(null, 'manutenção');
        $broken = $this->service->listPaginated(null, 'avariado');

        $this->assertEquals(1, $operational->total());
        $this->assertEquals('Operacional', $operational->items()[0]->name);
        $this->assertEquals(1, $maintenance->total());
        $this->assertEquals('Em Manutenção', $maintenance->items()[0]->name);
        $this->assertEquals(1, $broken->total());
        $this->assertEquals('Avariado', $broken->items()[0]->name);
    }

    #[Test]
    public function it_returns_an_empty_paginator_when_there_is_no_equipment(): void
    {
        $result = $this->service->listPaginated();

        $this->assertEquals(0, $result->total());
        $this->assertEmpty($result->items());
    }

    #[Test]
    public function it_orders_results_by_name_and_loads_the_room_relation(): void
    {
        $room = Room::factory()->create(['name' => 'Sala A']);
        Equipment::factory()->create(['name' => 'Beta', 'room_id' => $room->id]);
        Equipment::factory()->create(['name' => 'Alfa', 'room_id' => $room->id]);

        $result = $this->service->listPaginated();
        $items = $result->items();

        $this->assertEquals('Alfa', $items[0]->name);
        $this->assertEquals('Beta', $items[1]->name);
        $this->assertTrue(collect($items)->first()->relationLoaded('room'));
    }
}
