<?php

namespace App\Actions;

use App\DTOs\StoreEquipmentData;
use App\Models\Equipment;
use Illuminate\Support\Facades\DB;

final readonly class CreateEquipmentAction
{
    public function execute(StoreEquipmentData $data): Equipment
    {
        return DB::transaction(function () use ($data) {
            $equipment = Equipment::create([
                'name' => trim($data->name),
                'serial' => $data->serial ? strtoupper(trim($data->serial)) : null,
                'room_id' => $data->roomId,
                'category_id' => $data->categoryId,
                'active' => $data->active,
                'asset_tag' => $data->assetTag !== null ? trim($data->assetTag) : null,
                'brand' => $data->brand !== null ? trim($data->brand) : null,
                'model' => $data->model !== null ? trim($data->model) : null,
                'manufacturer' => $data->manufacturer !== null ? trim($data->manufacturer) : null,
                'purchase_date' => $data->purchaseDate,
                'warranty_until' => $data->warrantyUntil,
                'status' => $data->status !== null ? trim($data->status) : null,
                'notes' => $data->notes !== null ? trim($data->notes) : null,
            ]);

            return $equipment->load(['room', 'category']);
        });
    }
}
