<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final readonly class ScheduleMaintenanceData
{
    public function __construct(
        public string $title,
        public ?int $equipmentId,
        public string $scheduledAt,
        public ?int $assignedTo = null,
        public ?string $description = null,
    ) {
        if (trim($this->title) === '') {
            throw new InvalidArgumentException('Intervention title cannot be empty.');
        }

        if ($this->equipmentId === null || $this->equipmentId <= 0) {
            throw new InvalidArgumentException('Equipment is required.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        $rawTitle = $payload['title'] ?? '';
        $rawScheduledAt = $payload['scheduled_at'] ?? '';
        $rawDescription = $payload['description'] ?? null;
        $description = is_scalar($rawDescription) ? trim((string) $rawDescription) : '';

        return new self(
            title: trim(is_scalar($rawTitle) ? (string) $rawTitle : ''),
            equipmentId: self::parseNullableInt($payload['equipment_id'] ?? null),
            scheduledAt: is_scalar($rawScheduledAt) ? (string) $rawScheduledAt : '',
            assignedTo: self::parseNullableInt($payload['assigned_to'] ?? null),
            description: $description !== '' ? $description : null,
        );
    }

    private static function parseNullableInt(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return $parsed && $parsed > 0 ? $parsed : null;
    }
}
