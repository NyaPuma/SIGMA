<?php

namespace Tests\Unit\Services;

use App\Enums\StockMovementTypeEnum;
use App\Models\Part;
use App\Models\TaxRate;
use App\Services\LowStockAlertService;
use App\Services\NotificationCreatorService;
use App\Services\PartPriceCalculator;
use App\Services\StockMovementService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class StockServicesTest extends FeatureTestCase
{
    private PartPriceCalculator $priceCalculator;

    private StockMovementService $movementService;

    private LowStockAlertService $lowStockAlertService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priceCalculator = new PartPriceCalculator;
        $this->movementService = new StockMovementService;
        $this->lowStockAlertService = new LowStockAlertService(new NotificationCreatorService);
    }

    #[Test]
    public function price_with_vat_uses_the_part_tax_rate(): void
    {
        $tax = TaxRate::factory()->create(['percent' => 23]);
        $part = Part::factory()->create([
            'cost_price' => 100.00,
            'tax_rate_id' => $tax->id,
        ]);

        $this->assertSame(123.00, $this->priceCalculator->priceWithVat($part));
        $this->assertSame(23.00, $this->priceCalculator->vatAmount($part));
    }

    #[Test]
    public function price_with_vat_defaults_to_zero_when_no_tax_rate(): void
    {
        $part = Part::factory()->create([
            'cost_price' => 50.00,
            'tax_rate_id' => null,
        ]);

        $this->assertSame(50.00, $this->priceCalculator->priceWithVat($part));
    }

    #[Test]
    public function movement_out_reduces_part_stock_and_records_movement(): void
    {
        $admin = $this->createAdmin();
        $part = Part::factory()->create([
            'current_stock' => 30,
            'cost_price' => 20.00,
        ]);

        $movement = $this->movementService->record(
            part: $part,
            movementType: StockMovementTypeEnum::Out,
            quantity: 8,
            reason: 'Reparação',
            user: $admin,
        );

        $this->assertSame(22, $movement->stock_after);
        $this->assertSame(20.00, (float) $movement->unit_price_snapshot);
        $this->assertSame($admin->id, $movement->user_id);

        $part->refresh();
        $this->assertSame(22, $part->current_stock);
        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $part->id,
            'movement_type' => StockMovementTypeEnum::Out->value,
            'quantity' => 8,
            'stock_after' => 22,
        ]);
    }

    #[Test]
    public function movement_cannot_leave_stock_negative(): void
    {
        $part = Part::factory()->create(['current_stock' => 3]);

        $this->expectException(\InvalidArgumentException::class);

        $this->movementService->record(
            part: $part,
            movementType: StockMovementTypeEnum::Out,
            quantity: 5,
        );
    }

    #[Test]
    public function zero_quantity_movement_is_rejected(): void
    {
        $part = Part::factory()->create(['current_stock' => 10]);

        $this->expectException(\InvalidArgumentException::class);

        $this->movementService->record(
            part: $part,
            movementType: StockMovementTypeEnum::In,
            quantity: 0,
        );
    }

    #[Test]
    public function adjust_movement_applies_signed_delta(): void
    {
        $part = Part::factory()->create(['current_stock' => 10]);

        $this->movementService->record(
            part: $part,
            movementType: StockMovementTypeEnum::Adjust,
            quantity: -4,
            reason: 'Correção de contagem',
        );

        $part->refresh();
        $this->assertSame(6, $part->current_stock);
    }

    #[Test]
    public function low_stock_alert_only_returns_parts_at_or_below_minimum(): void
    {
        $low = Part::factory()->lowStock()->create();
        $healthy = Part::factory()->create([
            'current_stock' => 100,
            'min_stock' => 5,
        ]);

        $alerts = $this->lowStockAlertService->partsInAlert();

        $alertIds = array_map(fn (Part $p) => $p->id, $alerts);

        $this->assertContains($low->id, $alertIds);
        $this->assertNotContains($healthy->id, $alertIds);
    }

    #[Test]
    public function low_stock_alert_orders_parts_by_criticality(): void
    {
        $critical = Part::factory()->create([
            'current_stock' => 2,
            'min_stock' => 20,
        ]);
        $lessCritical = Part::factory()->create([
            'current_stock' => 15,
            'min_stock' => 20,
        ]);

        $alerts = $this->lowStockAlertService->partsInAlert();

        $this->assertSame($critical->id, $alerts[0]->id);
        $this->assertSame($lessCritical->id, $alerts[1]->id);
    }

    #[Test]
    public function low_stock_notification_is_created_for_admins(): void
    {
        $admin = $this->createAdmin();
        Part::factory()->lowStock()->create();

        $created = $this->lowStockAlertService->notifyAdminsForLowStock();

        $this->assertGreaterThan(0, $created);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'low_stock',
        ]);
    }

    #[Test]
    public function low_stock_notification_is_not_repeated_while_the_level_is_unchanged(): void
    {
        $this->createAdmin();
        $part = Part::factory()->lowStock()->create(['name' => 'Filtro de ar', 'current_stock' => 2, 'min_stock' => 5]);

        $this->assertGreaterThan(0, $this->lowStockAlertService->notifyAdminsForLowStock());
        $this->assertSame(0, $this->lowStockAlertService->notifyAdminsForLowStock());

        // A change in the stock level is a new situation and must alert again.
        $part->update(['current_stock' => 1]);
        $this->assertGreaterThan(0, $this->lowStockAlertService->notifyAdminsForLowStock());
    }

    #[Test]
    public function low_stock_notification_returns_zero_when_nothing_is_low(): void
    {
        $this->createAdmin();
        Part::factory()->create([
            'current_stock' => 100,
            'min_stock' => 5,
        ]);

        $this->assertSame(0, $this->lowStockAlertService->notifyAdminsForLowStock());
    }
}
