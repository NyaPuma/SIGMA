<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Equipment */
final class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'serial' => $this->serial,
            'asset_tag' => $this->asset_tag,
            'brand' => $this->brand,
            'model' => $this->model,
            'manufacturer' => $this->manufacturer,
            'purchase_date' => $this->purchase_date?->toIso8601String(),
            'warranty_until' => $this->warranty_until?->toIso8601String(),
            'status' => $this->status,
            'active' => $this->active,
            'notes' => $this->notes,
            'room_id' => $this->room_id,
            'category_id' => $this->category_id,
            'room' => $this->whenLoaded('room', fn () => $this->room ? new RoomResource($this->room) : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
