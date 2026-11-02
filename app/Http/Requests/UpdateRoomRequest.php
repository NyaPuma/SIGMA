<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trims whitespace from submitted data before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $name = $this->input('name');
            $this->merge([
                'name' => $this->filled('name') && is_scalar($name) ? trim((string) $name) : $name,
            ]);
        }

        if ($this->has('location')) {
            $location = $this->input('location');
            $this->merge([
                'location' => $this->filled('location') && is_scalar($location) ? trim((string) $location) : $location,
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Room|int|string|null $room */
        $room = $this->route('room') ?? $this->route('id');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique(Room::class, 'name')->ignore($room),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Room::class, 'code')->ignore($room)],
            'building' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Friendly attribute names for Laravel's error messages.
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
