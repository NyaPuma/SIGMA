<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdateEquipmentData
{
    public function __construct(
        public ?string $name = null,
        public ?string $serial = null,
        public ?int $roomId = null,
        public ?int $categoryId = null,
        public ?bool $active = null,
        public ?string $assetTag = null,
        public ?string $brand = null,
        public ?string $model = null,
        public ?string $manufacturer = null,
        public ?string $purchaseDate = null,
        public ?string $warrantyUntil = null,
        public ?string $status = null,
        public ?string $notes = null,
    ) {
        if ($this->name !== null && trim($this->name) === '') {
            throw new \InvalidArgumentException('Equipment name cannot be an empty string.');
        }

        if ($this->serial !== null && trim($this->serial) === '') {
            throw new \InvalidArgumentException('Serial number cannot be an empty string.');
        }

        if ($this->roomId !== null && $this->roomId <= 0) {
            throw new \InvalidArgumentException('Room ID must be a positive integer.');
        }

        if ($this->categoryId !== null && $this->categoryId <= 0) {
            throw new \InvalidArgumentException('Category ID must be a positive integer.');
        }

        if ($this->status !== null && ! in_array($this->status, StoreEquipmentData::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid equipment operational status.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        return new self(
            name: self::parseNullableString($payload['name'] ?? null),
            serial: self::parseSerial($payload['serial'] ?? null),
            roomId: self::parseNullableInt($payload['room_id'] ?? null),
            categoryId: self::parseNullableInt($payload['category_id'] ?? null),
            active: self::parseNullableBool($payload['active'] ?? null),
            assetTag: self::parseNullableString($payload['asset_tag'] ?? null),
            brand: self::parseNullableString($payload['brand'] ?? null),
            model: self::parseNullableString($payload['model'] ?? null),
            manufacturer: self::parseNullableString($payload['manufacturer'] ?? null),
            purchaseDate: self::parseNullableString($payload['purchase_date'] ?? null),
            warrantyUntil: self::parseNullableString($payload['warranty_until'] ?? null),
            status: in_array($payload['status'] ?? null, StoreEquipmentData::STATUSES, true)
                ? $payload['status']
                : null,
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
     * Sanitize and upper-case the serial number when provided.
     */
    private static function parseSerial(mixed $value): ?string
    {
        $string = self::parseNullableString($value);

        return $string !== null ? strtoupper($string) : null;
    }

    /**
     * Sanitize optional integer IDs, converting "", 0, or invalid values to null.
     */
    private static function parseNullableInt(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return $parsed && $parsed > 0 ? $parsed : null;
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
            'serial' => $this->serial,
            'room_id' => $this->roomId,
            'category_id' => $this->categoryId,
            'active' => $this->active,
            'asset_tag' => $this->assetTag,
            'brand' => $this->brand,
            'model' => $this->model,
            'manufacturer' => $this->manufacturer,
            'purchase_date' => $this->purchaseDate,
            'warranty_until' => $this->warrantyUntil,
            'status' => $this->status,
            'notes' => $this->notes,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function hasUpdates(): bool
    {
        return ! empty($this->toArray());
    }
}
