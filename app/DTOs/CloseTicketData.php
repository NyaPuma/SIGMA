<?php

namespace App\DTOs;

use App\Support\MoneyInput;
use Illuminate\Foundation\Http\FormRequest;

final readonly class CloseTicketData
{
    public function __construct(
        public float $actualCost,
        public ?string $report = null,
        public bool $force = false,
    ) {
        if ($this->actualCost < 0) {
            throw new \InvalidArgumentException('The actual cost for closing a ticket cannot be negative.');
        }
    }

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        // Sanitize report: trim whitespace and convert empty string to null
        $rawReport = isset($payload['report']) && is_string($payload['report']) ? trim($payload['report']) : null;
        $report = $rawReport !== '' ? $rawReport : null;

        // Normalize cost to 2 decimal places
        $actualCost = self::parseCost($payload['actual_cost'] ?? 0);

        // Flexible boolean coercion for 'force' flag ("true", "1", true, etc.)
        $force = filter_var($payload['force'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new self(
            actualCost: $actualCost,
            report: $report,
            force: $force,
        );
    }

    /**
     * Sanitize monetary input to a precise float (2 decimal places).
     */
    private static function parseCost(mixed $value): float
    {
        return MoneyInput::parse($value);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'actual_cost' => $this->actualCost,
            'report' => $this->report,
            'force' => $this->force,
        ];
    }
}
