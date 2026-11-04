<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementTypeEnum;
use App\Models\Part;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock module statistics and reports.
 */
final class StockDashboardService
{
    /**
     * Total stock value in warehouse (sum of current_stock * cost_price).
     */
    public function totalStockValue(): float
    {
        $value = Part::query()
            ->active()
            ->selectRaw('COALESCE(SUM(current_stock * cost_price), 0) as total')
            ->value('total');

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Total distinct parts in catalog.
     */
    public function totalParts(): int
    {
        return (int) Part::query()->active()->count();
    }

    /**
     * Number of parts in low stock alert.
     */
    public function lowStockCount(): int
    {
        return (int) Part::query()->lowStock()->count();
    }

    /**
     * Parts with stagnant stock (no movement for X days) — tied-up capital.
     *
     * @return Collection<int, Part>
     */
    public function slowMovingParts(int $inactiveDays = 90, int $limit = 20): Collection
    {
        $cutoff = now()->subDays($inactiveDays);

        return Part::query()
            ->active()
            ->where('current_stock', '>', 0)
            ->whereDoesntHave('movements', fn ($q) => $q->where('created_at', '>=', $cutoff))
            ->with('category')
            ->orderByDesc('current_stock')
            ->limit($limit)
            ->get();
    }

    /**
     * Top N most consumed parts (exits) in a period.
     *
     * @return Collection<int, array{part_id: int, part_name: string, sku: string, total_quantity: int, total_value: float}>
     */
    public function topConsumed(?string $from = null, ?string $to = null, int $limit = 10): Collection
    {
        $rows = $this->consumptionQuery($from, $to)
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        /** @var Collection<int, object> $rows */
        return $rows->map(fn (object $row) => [
            'part_id' => is_numeric($row->part_id ?? null) ? (int) $row->part_id : 0,
            'part_name' => is_string($row->part_name ?? null) ? (string) $row->part_name : '',
            'sku' => is_string($row->sku ?? null) ? (string) $row->sku : '',
            'total_quantity' => is_numeric($row->total_quantity ?? null) ? (int) $row->total_quantity : 0,
            'total_value' => round(is_numeric($row->total_value ?? null) ? (float) $row->total_value : 0.0, 2),
        ]);
    }

    /**
     * Cost of parts consumed by equipment.
     *
     * @return Collection<int, array{equipment_id: int|null, equipment_name: string|null, total_quantity: int, total_value: float}>
     */
    public function costByEquipment(?string $from = null, ?string $to = null, int $limit = 20): Collection
    {
        $rows = $this->consumptionQuery($from, $to)
            ->whereNotNull('stock_movements.equipment_id')
            ->select(
                'stock_movements.equipment_id',
                'equipments.name as equipment_name',
                DB::raw('SUM(ABS(stock_movements.quantity)) as total_quantity'),
                DB::raw('COALESCE(SUM(ABS(stock_movements.quantity) * stock_movements.unit_price_snapshot), 0) as total_value')
            )
            ->groupBy('stock_movements.equipment_id', 'equipments.name')
            ->orderByDesc('total_value')
            ->limit($limit)
            ->get();

        /** @var Collection<int, object> $rows */
        return $rows->map(fn (object $row) => [
            'equipment_id' => isset($row->equipment_id) && is_numeric($row->equipment_id) ? (int) $row->equipment_id : null,
            'equipment_name' => isset($row->equipment_name) && is_string($row->equipment_name) ? $row->equipment_name : null,
            'total_quantity' => is_numeric($row->total_quantity ?? null) ? (int) $row->total_quantity : 0,
            'total_value' => round(is_numeric($row->total_value ?? null) ? (float) $row->total_value : 0.0, 2),
        ]);
    }

    /**
     * Cost of parts consumed by ticket/intervention.
     *
     * @return Collection<int, array{ticket_id: int, ticket_reference: string, total_quantity: int, total_value: float}>
     */
    public function costByTicket(?string $from = null, ?string $to = null, int $limit = 20): Collection
    {
        $rows = $this->consumptionQuery($from, $to)
            ->whereNotNull('stock_movements.ticket_id')
            ->select(
                'stock_movements.ticket_id',
                'tickets.reference as ticket_reference',
                DB::raw('SUM(ABS(stock_movements.quantity)) as total_quantity'),
                DB::raw('COALESCE(SUM(ABS(stock_movements.quantity) * stock_movements.unit_price_snapshot), 0) as total_value')
            )
            ->groupBy('stock_movements.ticket_id', 'tickets.reference')
            ->orderByDesc('total_value')
            ->limit($limit)
            ->get();

        /** @var Collection<int, object> $rows */
        return $rows->map(fn (object $row) => [
            'ticket_id' => is_numeric($row->ticket_id ?? null) ? (int) $row->ticket_id : 0,
            'ticket_reference' => is_string($row->ticket_reference ?? null) ? (string) $row->ticket_reference : '',
            'total_quantity' => is_numeric($row->total_quantity ?? null) ? (int) $row->total_quantity : 0,
            'total_value' => round(is_numeric($row->total_value ?? null) ? (float) $row->total_value : 0.0, 2),
        ]);
    }

    /**
     * Simple stockout forecast based on average monthly consumption.
     *
     * @return Collection<int, array{part_id: int, part_name: string, sku: string, current_stock: int, avg_monthly_usage: float, est_months_of_stock: float}>
     */
    public function stockRunoutForecast(int $months = 3, int $limit = 50): Collection
    {
        // startOfMonth() first: subMonths() on the 29th-31st overflows into the next month.
        $from = now()->startOfMonth()->subMonths($months);

        $usageRaw = $this->consumptionQuery($from->toDateString(), now()->toDateString())
            ->select(
                'stock_movements.part_id',
                DB::raw('SUM(ABS(stock_movements.quantity)) as total_quantity')
            )
            ->groupBy('stock_movements.part_id')
            ->pluck('total_quantity', 'part_id')
            ->map(fn ($q) => is_numeric($q) ? (float) $q : 0.0);
        /** @var Collection<int, float> $usage */
        $usage = $usageRaw;

        /** @var Collection<int, array{part_id: int, part_name: string, sku: string, current_stock: int, avg_monthly_usage: float, est_months_of_stock: float}> $forecast */
        $forecast = Part::query()
            ->active()
            ->where('current_stock', '>', 0)
            ->get(['id', 'sku', 'name', 'current_stock'])
            ->filter(fn (Part $part) => ($usage[$part->id] ?? 0) > 0)
            ->map(function (Part $part) use ($usage, $months) {
                $avgMonthly = ($usage[$part->id] ?? 0) / max(1, $months);
                $monthsOfStock = $avgMonthly > 0 ? (int) $part->current_stock / $avgMonthly : PHP_FLOAT_MAX;

                return [
                    'part_id' => (int) $part->id,
                    'part_name' => $part->name,
                    'sku' => $part->sku,
                    'current_stock' => (int) $part->current_stock,
                    'avg_monthly_usage' => round($avgMonthly, 2),
                    'est_months_of_stock' => round(min($monthsOfStock, PHP_FLOAT_MAX), 1),
                ];
            })
            ->sortBy('est_months_of_stock')
            ->take($limit)
            ->values();

        return $forecast;
    }

    /**
     * Base consumption query: exit movements (and negative adjustments) in a period.
     */
    /**
     * @return Builder<StockMovement>
     */
    private function consumptionQuery(?string $from = null, ?string $to = null): Builder
    {
        $query = StockMovement::query()
            ->leftJoin('parts', 'parts.id', '=', 'stock_movements.part_id')
            ->leftJoin('equipments', 'equipments.id', '=', 'stock_movements.equipment_id')
            ->leftJoin('tickets', 'tickets.id', '=', 'stock_movements.ticket_id')
            ->whereIn('stock_movements.movement_type', [
                StockMovementTypeEnum::Out->value,
                StockMovementTypeEnum::Adjust->value,
            ])
            ->where('stock_movements.quantity', '<', 0);

        if ($from !== null && $from !== '') {
            $query->where('stock_movements.created_at', '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->where('stock_movements.created_at', '<=', $to.' 23:59:59');
        }

        $query->select(
            'stock_movements.part_id',
            'parts.name as part_name',
            'parts.sku as sku',
            DB::raw('SUM(ABS(stock_movements.quantity)) as total_quantity'),
            DB::raw('COALESCE(SUM(ABS(stock_movements.quantity) * stock_movements.unit_price_snapshot), 0) as total_value')
        );

        $query->groupBy('stock_movements.part_id', 'parts.name', 'parts.sku');

        return $query;
    }
}
