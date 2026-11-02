<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trims extra whitespace from name and serial number before validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $serial = $this->input('serial');

        $this->merge([
            'name' => $this->filled('name') && is_scalar($name) ? trim((string) $name) : $name,
            'serial' => $this->filled('serial') && is_scalar($serial) ? trim((string) $serial) : $serial,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'serial' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Equipment::class, 'serial'),
            ],
            'room_id' => ['nullable', 'integer', Rule::exists(Room::class, 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists(EquipmentCategory::class, 'id')],
            'active' => ['sometimes', 'boolean'],
            'asset_tag' => ['nullable', 'string', 'max:100', Rule::unique(Equipment::class, 'asset_tag')],
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
     * Friendly names for fields in default validation messages.
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
