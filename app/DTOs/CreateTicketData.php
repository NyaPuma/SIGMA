<?php

namespace App\DTOs;

use App\Enums\TicketPriorityEnum;
use Illuminate\Foundation\Http\FormRequest;

final readonly class CreateTicketData
{
    public function __construct(
        public string $title,
        public string $description,
        public TicketPriorityEnum $priority,
        public ?int $equipmentId = null,
        public ?int $roomId = null,
    ) {
        if (trim($this->title) === '') {
            throw new \InvalidArgumentException('Ticket title cannot be empty.');
        }

        if (trim($this->description) === '') {
            throw new \InvalidArgumentException('Ticket description cannot be empty.');
        }

        if ($this->equipmentId !== null && $this->equipmentId <= 0) {
            throw new \InvalidArgumentException('Equipment ID must be a positive integer.');
        }

        if ($this->roomId !== null && $this->roomId <= 0) {
            throw new \InvalidArgumentException('Room ID must be a positive integer.');
        }
    }

    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        // Accept enum instances and normalize scalar priority values.
        $rawPriority = $payload['priority'] ?? TicketPriorityEnum::Low;
        $priority = $rawPriority instanceof TicketPriorityEnum
            ? $rawPriority
            : (TicketPriorityEnum::normalize($rawPriority) ?? TicketPriorityEnum::Low);

        return new self(
            title: trim((string) ($payload['title'] ?? '')),
            description: trim((string) ($payload['description'] ?? '')),
            priority: $priority,
            equipmentId: self::parseNullableInt($payload['equipment_id'] ?? null),
            roomId: self::parseNullableInt($payload['room_id'] ?? null),
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

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'equipment_id' => $this->equipmentId,
            'room_id' => $this->roomId,
        ];
    }
}
