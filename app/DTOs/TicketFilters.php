<?php

namespace App\DTOs;

use App\Enums\TicketPriorityEnum;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class TicketFilters
{
    public function __construct(
        public ?string $query = null,
        public ?TicketPriorityEnum $priority = null,
        public ?string $status = null,
        public ?CarbonImmutable $dateFrom = null,
        public ?CarbonImmutable $dateTo = null,
        public ?int $userId = null,
        public ?int $technicianId = null,
        public ?int $equipmentId = null,
        public ?int $roomId = null,
    ) {
        if ($this->dateFrom !== null && $this->dateTo !== null && $this->dateFrom->isAfter($this->dateTo)) {
            throw new \InvalidArgumentException('dateFrom cannot be later than dateTo.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        return new self(
            query: self::parseNullableString($payload['q'] ?? $payload['query'] ?? null),
            priority: self::parsePriority($payload['priority'] ?? null),
            status: self::parseNullableString($payload['status'] ?? null),
            dateFrom: self::parseDate($payload['date_from'] ?? null),
            dateTo: self::parseDate($payload['date_to'] ?? null),
            userId: self::parseNullableInt($payload['user_id'] ?? null),
            technicianId: self::parseNullableInt($payload['technician_id'] ?? null),
            equipmentId: self::parseNullableInt($payload['equipment_id'] ?? null),
            roomId: self::parseNullableInt($payload['room_id'] ?? null),
        );
    }

    /**
     * Safely coerce input to TicketPriorityEnum.
     */
    private static function parsePriority(mixed $value): ?TicketPriorityEnum
    {
        if ($value instanceof TicketPriorityEnum) {
            return $value;
        }

        if (empty($value) || (! is_string($value) && ! is_int($value))) {
            return null;
        }

        return TicketPriorityEnum::normalize($value);
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
     * Convert query-string numeric input to valid positive integers.
     */
    private static function parseNullableInt(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return $parsed && $parsed > 0 ? $parsed : null;
    }

    /**
     * Convert date input to CarbonImmutable. Returns null for empty/un-parseable values
     * rather than throwing, to handle loose query-string input gracefully.
     */
    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns only active (non-null) filters for dynamic Eloquent query building.
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'q' => $this->query,
            'priority' => $this->priority?->value,
            'status' => $this->status,
            'date_from' => $this->dateFrom?->toDateString(),
            'date_to' => $this->dateTo?->toDateString(),
            'user_id' => $this->userId,
            'technician_id' => $this->technicianId,
            'equipment_id' => $this->equipmentId,
            'room_id' => $this->roomId,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function hasFilters(): bool
    {
        return ! empty($this->toArray());
    }
}
