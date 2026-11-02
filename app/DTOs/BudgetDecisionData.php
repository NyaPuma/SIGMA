<?php

namespace App\DTOs;

use App\Enums\BudgetDecisionEnum;
use Illuminate\Foundation\Http\FormRequest;

final readonly class BudgetDecisionData
{
    public function __construct(
        public BudgetDecisionEnum $decision,
        public ?string $feedback = null,
    ) {}

    /** @param FormRequest|array<string, mixed> $data */
    public static function fromRequest(FormRequest|array $data): self
    {
        $payload = $data instanceof FormRequest ? $data->validated() : $data;

        // Accepts 'decision' or falls back to 'action'
        $rawDecision = $payload['decision'] ?? $payload['action'] ?? null;

        if ($rawDecision === null) {
            $decision = BudgetDecisionEnum::Approve;
        } elseif ($rawDecision instanceof BudgetDecisionEnum) {
            $decision = $rawDecision;
        } elseif (is_int($rawDecision) || is_string($rawDecision)) {
            $decision = BudgetDecisionEnum::from($rawDecision);
        } else {
            $decision = BudgetDecisionEnum::Approve;
        }

        // Sanitize feedback: trim whitespace and convert empty strings to null
        $rawFeedbackValue = $payload['feedback'] ?? null;
        $rawFeedback = is_scalar($rawFeedbackValue) ? trim((string) $rawFeedbackValue) : null;
        $feedback = $rawFeedback !== null && $rawFeedback !== '' ? $rawFeedback : null;

        return new self(
            decision: $decision,
            feedback: $feedback,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision->value,
            'feedback' => $this->feedback,
        ];
    }

    public function isApproved(): bool
    {
        return $this->decision === BudgetDecisionEnum::Approve;
    }

    public function isRejected(): bool
    {
        return $this->decision === BudgetDecisionEnum::Reject;
    }
}
