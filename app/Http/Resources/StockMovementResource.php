<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StockMovementTypeEnum;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockMovement */
final class StockMovementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $type = StockMovementTypeEnum::normalize((string) $this->movement_type);

        return [
            'id' => $this->id,
            'part_id' => $this->part_id,
            'part' => $this->whenLoaded('part', fn () => new PartResource($this->part)),
            'ticket_id' => $this->ticket_id,
            'ticket' => $this->whenLoaded('ticket', fn () => new TicketResource($this->ticket)),
            'equipment_id' => $this->equipment_id,
            'equipment' => $this->whenLoaded('equipment', fn () => new EquipmentResource($this->equipment)),
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'movement_type' => $this->movement_type,
            'movement_type_label' => $type?->label(),
            'movement_type_icon' => $type?->icon(),
            'delta' => $this->delta(),
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'unit_price_snapshot' => $this->unit_price_snapshot,
            'stock_after' => $this->stock_after,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
