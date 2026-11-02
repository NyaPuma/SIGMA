<?php

namespace App\DTOs;

use App\Support\MoneyInput;
use Illuminate\Foundation\Http\FormRequest;

final readonly class BudgetSubmissionData
{
    public function __construct(
        public float $estimatedBudget,
        public ?array $budgetDetails = null,
        public bool $isDetailedRequest = false,
    ) {
        if ($this->estimatedBudget < 0) {
            throw new \InvalidArgumentException('The estimated budget amount cannot be negative.');
        }
    }

    /**
     * Factory for a simple budget estimate submission.
     */
    /** @param FormRequest|array<string, mixed> $data */
    public static function fromSubmitEstimate(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        return new self(
            estimatedBudget: self::parseAmount($payload['estimatedBudget'] ?? $payload['estimated_budget'] ?? 0),
            budgetDetails: $payload['budget_details'] ?? null,
            isDetailedRequest: false,
        );
    }

    /**
     * Factory for a detailed budget request.
     */
    /** @param FormRequest|array<string, mixed> $data */
    public static function fromDetailedRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        return new self(
            estimatedBudget: self::parseAmount($payload['budget_amount'] ?? 0),
            budgetDetails: $payload['budget_details'] ?? null,
            isDetailedRequest: true,
        );
    }

    private static function parseAmount(mixed $value): float
    {
        return MoneyInput::parse($value);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'estimated_budget' => $this->estimatedBudget,
            'budget_details' => $this->budgetDetails,
            'is_detailed_request' => $this->isDetailedRequest,
        ];
    }
}
