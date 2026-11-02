<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class ScheduleTicketData
{
    public function __construct(
        public CarbonImmutable $scheduledAt,
        public ?CarbonImmutable $scheduledEnd = null,
    ) {
        if ($this->scheduledEnd !== null && $this->scheduledEnd->isBefore($this->scheduledAt)) {
            throw new \InvalidArgumentException('The scheduled end date cannot be earlier than the start date.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        $scheduledAt = self::parseDate($payload['scheduled_at'] ?? null);

        if ($scheduledAt === null) {
            throw new \InvalidArgumentException('The scheduled_at date is required and must be a valid datetime.');
        }

        return new self(
            scheduledAt: $scheduledAt,
            scheduledEnd: self::parseDate($payload['scheduled_end'] ?? null),
        );
    }

    /**
     * Convert strings, DateTimeInterface instances, or timestamps to CarbonImmutable.
     * Returns null for empty / un-parseable input rather than throwing.
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
            return CarbonImmutable::parse(is_string($value) ? $value : (string) (is_scalar($value) ? $value : ''));
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'scheduled_at' => $this->scheduledAt->toDateTimeString(),
            'scheduled_end' => $this->scheduledEnd?->toDateTimeString(),
        ];
    }
}
