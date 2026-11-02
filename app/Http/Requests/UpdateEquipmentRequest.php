<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trims whitespace from submitted data.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => $this->filled('name') ? trim((string) $this->name) : $this->name,
            ]);
        }

        if ($this->has('serial')) {
            $this->merge([
                'serial' => $this->filled('serial') ? trim((string) $this->serial) : $this->serial,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Equipment|int|string|null $equipment */
        $equipment = $this->route('equipment') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'serial' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique(Equipment::class, 'serial')->ignore($equipment),
            ],
            'room_id' => ['nullable', 'integer', Rule::exists(Room::class, 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists(EquipmentCategory::class, 'id')],
            'active' => ['sometimes', 'boolean'],
            'asset_tag' => ['nullable', 'string', 'max:100', Rule::unique(Equipment::class, 'asset_tag')->ignore($equipment)],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['operacional', 'manutenção', 'avariado', 'abatido'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Friendly attribute names for Laravel's error messages.
     */
    public function attributes(): array
    {
        return [
            'name' => 'equipment name',
            'serial' => 'serial number',
            'room_id' => 'room',
            'category_id' => 'category',
            'active' => 'active status',
            'asset_tag' => 'asset tag',
            'brand' => 'brand',
            'model' => 'model',
            'manufacturer' => 'manufacturer',
            'purchase_date' => 'purchase date',
            'warranty_until' => 'warranty expiry',
            'status' => 'operational status',
            'notes' => 'notes',
        ];
    }
}
