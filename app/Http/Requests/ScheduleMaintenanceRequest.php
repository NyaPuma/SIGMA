<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScheduleMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Trims whitespace from text fields before validation.
     */
    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $description = $this->input('description');

        $this->merge([
            'title' => $this->filled('title') && is_scalar($title) ? trim((string) $title) : $title,
            'description' => $this->filled('description') && is_scalar($description) ? trim((string) $description) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'equipment_id' => ['required', 'integer', Rule::exists(Equipment::class, 'id')],
            'scheduled_at' => ['required', 'date'],
            'assigned_to' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'intervention title',
            'equipment_id' => 'equipment',
            'scheduled_at' => 'date and time',
            'assigned_to' => 'technician',
        ];
    }
}
