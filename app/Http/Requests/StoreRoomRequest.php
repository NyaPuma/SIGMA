<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trims whitespace before validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $location = $this->input('location');

        $this->merge([
            'name' => $this->filled('name') && is_scalar($name) ? trim((string) $name) : $name,
            'location' => $this->filled('location') && is_scalar($location) ? trim((string) $location) : $location,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Room::class, 'name'),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Room::class, 'code')],
            'building' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Maps field names to Laravel's default messages.
     */
    public function attributes(): array
    {
        return [
            'name' => 'room name',
            'location' => 'location',
            'code' => 'room code',
            'building' => 'building',
            'floor' => 'floor',
            'capacity' => 'capacity',
            'description' => 'description',
            'notes' => 'internal notes',
            'active' => 'active status',
        ];
    }
}
