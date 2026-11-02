<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class StoreRoomData
{
    public function __construct(
        public string $name,
        public ?string $code = null,
        public ?string $location = null,
        public bool $active = true,
        public ?string $building = null,
        public ?string $floor = null,
        public ?int $capacity = null,
        public ?string $description = null,
        public ?string $notes = null,
    ) {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Room name cannot be empty.');
        }

        if ($this->capacity !== null && $this->capacity < 0) {
            throw new \InvalidArgumentException('Room capacity cannot be negative.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        return new self(
            name: trim(is_string($payload['name'] ?? null) ? (string) $payload['name'] : ''),
            code: self::parseCode($payload['code'] ?? null),
            location: self::parseNullableString($payload['location'] ?? null),
            active: self::parseBool($payload['active'] ?? true),
            building: self::parseNullableString($payload['building'] ?? null),
            floor: self::parseNullableString($payload['floor'] ?? null),
            capacity: self::parseNullableInt($payload['capacity'] ?? null),
            description: self::parseNullableString($payload['description'] ?? null),
            notes: self::parseNullableString($payload['notes'] ?? null),
        );
    }

    /**
     * Sanitize optional strings, converting "" or whitespace-only values to null.
     */
    private static function parseNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Sanitize and upper-case the room code (e.g. "lab-1" -> "LAB-1").
     */
    private static function parseCode(mixed $value): ?string
    {
        $string = self::parseNullableString($value);

        return $string !== null ? strtoupper($string) : null;
    }

    /**
     * Sanitize optional integers, converting "", or invalid values to null.
     */
    private static function parseNullableInt(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return $parsed !== null && $parsed >= 0 ? $parsed : null;
    }

    /**
     * Normalize boolean input — handles "true", "1", "on", etc.
     * Defaults to true when null is passed.
     */
    private static function parseBool(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'location' => $this->location,
            'active' => $this->active,
            'building' => $this->building,
            'floor' => $this->floor,
            'capacity' => $this->capacity,
            'description' => $this->description,
            'notes' => $this->notes,
        ];
    }
}
