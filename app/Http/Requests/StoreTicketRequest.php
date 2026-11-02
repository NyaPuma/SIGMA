<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TicketPriorityEnum;
use App\Models\Equipment;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'description' => $this->filled('description') && is_scalar($description) ? trim((string) $description) : $description,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(TicketPriorityEnum::acceptedValues())],
            'equipment_id' => ['nullable', 'integer', Rule::exists(Equipment::class, 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists(Room::class, 'id')],
        ];
    }

    /**
     * Friendly names for default error messages.
     */
    public function attributes(): array
    {
        return [
            'title' => 'title',
            'description' => 'description',
            'priority' => 'priority',
            'equipment_id' => 'equipment',
            'room_id' => 'room',
        ];
    }
}
