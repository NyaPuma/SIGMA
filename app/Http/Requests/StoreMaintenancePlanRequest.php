<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MaintenancePlanIntervalTypeEnum;
use App\Models\Equipment;
use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMaintenancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'integer', Rule::exists(Equipment::class, 'id')],
            'name' => ['required', 'string', 'max:150'],
            'interval_type' => ['required', Rule::in(MaintenancePlanIntervalTypeEnum::values())],
            'interval_value' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'parts' => ['sometimes', 'array'],
            'parts.*' => ['array'],
            'parts.*.part_id' => ['required', 'integer', Rule::exists(Part::class, 'id')],
            'parts.*.expected_quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'equipment_id' => 'equipment',
            'name' => 'name',
            'interval_type' => 'interval type',
            'interval_value' => 'interval',
            'description' => 'description',
            'active' => 'active',
            'parts' => 'parts',
        ];
    }
}
