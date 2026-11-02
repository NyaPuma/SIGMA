<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdateRoomData
{
    public function __construct(
        public ?string $name = null,
        public ?string $code = null,
        public ?string $location = null,
        public ?bool $active = null,
        public ?string $building = null,
        public ?string $floor = null,
        public ?int $capacity = null,
        public ?string $description = null,
        public ?string $notes = null,
    ) {
        if ($this->name !== null && trim($this->name) === '') {
            throw new \InvalidArgumentException('Room name cannot be an empty string.');
        }

        if ($this->code !== null && trim($this->code) === '') {
            throw new \InvalidArgumentException('Room code cannot be an empty string.');
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
            name: self::parseNullableString($payload['name'] ?? null),
            code: self::parseCode($payload['code'] ?? null),
            location: self::parseNullableString($payload['location'] ?? null),
            active: self::parseNullableBool($payload['active'] ?? null),
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
     * Sanitize optional integers, converting "" or invalid values to null.
     */
    private static function parseNullableInt(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return $parsed !== null && $parsed >= 0 ? $parsed : null;
    }

    /**
     * Normalize boolean input without forcing a default — null input stays null.
     */
    private static function parseNullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Returns only the fields that were provided for partial Eloquent updates.
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'code' => $this->code,
            'location' => $this->location,
            'active' => $this->active,
            'building' => $this->building,
            'floor' => $this->floor,
            'capacity' => $this->capacity,
            'description' => $this->description,
            'notes' => $this->notes,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function hasUpdates(): bool
    {
        return ! empty($this->toArray());
    }
}
