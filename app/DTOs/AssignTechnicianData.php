<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class AssignTechnicianData
{
    public function __construct(
        public ?int $technicianId,
    ) {
        // Optional: defensive validation to ensure that if an ID is provided, it is a positive integer (> 0)
        if ($this->technicianId !== null && $this->technicianId <= 0) {
            throw new \InvalidArgumentException('Technician ID must be a positive integer.');
        }
    }

    /**
     * Creates the DTO from an Array or FormRequest.
     */
    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        $rawId = $payload['technician_id'] ?? null;

        // Treats empty strings (''), null, or non-numeric values as null
        $technicianId = filter_var($rawId, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return new self(
            technicianId: $technicianId && $technicianId > 0 ? $technicianId : null
        );
    }

    /**
     * Converts the DTO to an array for use in Models or Services.
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'technician_id' => $this->technicianId,
        ];
    }
}
