<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;

final class BudgetCalculatorService
{
    /**
     * Calculates the total cost of materials associated with the ticket budget.
     */
    public function calculateTotalMaterialCost(Ticket $ticket): float
    {
        return $this->calculateByType($ticket, 'material');
    }

    /**
     * Calculates the total labor cost associated with the ticket budget.
     */
    public function calculateTotalLaborCost(Ticket $ticket): float
    {
        return $this->calculateByType($ticket, 'labor');
    }

    /**
     * Calculates the total global budget amount (materials + labor).
     */
    public function calculateBudgetTotal(Ticket $ticket): float
    {
        return $this->calculateTotalMaterialCost($ticket) + $this->calculateTotalLaborCost($ticket);
    }

    /**
     * Returns the detailed breakdown of ticket budget items.
     *
     * @return array{
     *     materials: array<int, array<string, mixed>>,
     *     labor: array<int, array<string, mixed>>,
     *     material_total: float,
     *     labor_total: float,
     *     grand_total: float
     * }
     */
    public function getBreakdown(Ticket $ticket): array
    {
        $materialItems = [];
        $laborItems = [];
        $details = is_array($ticket->budget_details) ? $ticket->budget_details : [];

        foreach ($details as $item) {
            $type = is_string($item['type'] ?? null) ? $item['type'] : 'material';

            if ($type === 'labor') {
                $hours = is_numeric($item['hours'] ?? null) ? (float) $item['hours'] : 0.0;
                $rate = is_numeric($item['hourly_rate'] ?? null) ? (float) $item['hourly_rate'] : 0.0;
                $subtotal = $hours * $rate;
                $laborItems[] = array_merge($item, ['subtotal' => $subtotal]);
            } else {
                $qty = is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 0.0;
                $price = is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : 0.0;
                $subtotal = $qty * $price;
                $materialItems[] = array_merge($item, ['subtotal' => $subtotal]);
            }
        }

        $materialSum = collect($materialItems)->sum('subtotal');
        $laborSum = collect($laborItems)->sum('subtotal');
        $materialTotal = is_numeric($materialSum) ? (float) $materialSum : 0.0;
        $laborTotal = is_numeric($laborSum) ? (float) $laborSum : 0.0;

        return [
            'materials' => $materialItems,
            'labor' => $laborItems,
            'material_total' => $materialTotal,
            'labor_total' => $laborTotal,
            'grand_total' => $materialTotal + $laborTotal,
        ];
    }

    /**
     * Calculates the cost of a specific budget item type.
     */
    private function calculateByType(Ticket $ticket, string $type): float
    {
        $total = 0.0;
        $details = is_array($ticket->budget_details) ? $ticket->budget_details : [];

        foreach ($details as $item) {
            $itemType = is_string($item['type'] ?? null) ? $item['type'] : 'material';

            if ($itemType === $type) {
                if ($type === 'labor') {
                    $hours = is_numeric($item['hours'] ?? null) ? (float) $item['hours'] : 0.0;
                    $rate = is_numeric($item['hourly_rate'] ?? null) ? (float) $item['hourly_rate'] : 0.0;
                    $subtotal = $hours * $rate;
                } else {
                    $qty = is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 0.0;
                    $price = is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : 0.0;
                    $subtotal = $qty * $price;
                }

                $total += $subtotal;
            }
        }

        return (float) $total;
    }
}
