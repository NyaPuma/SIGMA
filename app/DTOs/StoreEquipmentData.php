<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class StoreEquipmentData
{
    /**
     * Valid operational status values stored in the database.
     * These are Portuguese data values — do NOT rename; they match DB enum column values.
     */
    public const STATUSES = [
        'operacional',
        'manutenção',
        'avariado',
        'abatido',
    ];

    public function __construct(
        public string $name,
        public string $serial,
        public ?int $roomId = null,
        public ?int $categoryId = null,
        public bool $active = true,
        public ?string $assetTag = null,
        public ?string $brand = null,
        public ?string $model = null,
        public ?string $manufacturer = null,
        public ?string $purchaseDate = null,
        public ?string $warrantyUntil = null,
        public string $status = 'operacional',
        public ?string $notes = null,
    ) {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Equipment name cannot be empty.');
        }

        if (trim($this->serial) === '') {
            throw new \InvalidArgumentException('Serial number cannot be empty.');
        }

        if ($this->roomId !== null && $this->roomId <= 0) {
            throw new \InvalidArgumentException('Room ID must be a positive integer.');
        }

        if ($this->categoryId !== null && $this->categoryId <= 0) {
            throw new \InvalidArgumentException('Category ID must be a positive integer.');
        }

        if (! in_array($this->status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid equipment operational status.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        $status = $payload['status'] ?? 'operacional';
        if (! in_array($status, self::STATUSES, true)) {
            $status = 'operacional';
        }

        return new self(
            name: trim((string) ($payload['name'] ?? '')),
            serial: strtoupper(trim((string) ($payload['serial'] ?? ''))),
            roomId: self::parseNullableInt($payload['room_id'] ?? null),
            categoryId: self::parseNullableInt($payload['category_id'] ?? null),
            active: self::parseBool($payload['active'] ?? true),
            assetTag: self::parseNullableString($payload['asset_tag'] ?? null),
            brand: self::parseNullableString($payload['brand'] ?? null),
            model: self::parseNullableString($payload['model'] ?? null),
            manufacturer: self::parseNullableString($payload['manufacturer'] ?? null),
            purchaseDate: self::parseNullableString($payload['purchase_date'] ?? null),
            warrantyUntil: self::parseNullableString($payload['warranty_until'] ?? null),
            status: $status,
            notes: self::parseNullableString($payload['notes'] ?? null),
        );
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
     * Normalize boolean input — handles "true", "1", "on", etc.
     * Defaults to true when null is passed (active by default).
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
        ];
    }
}
