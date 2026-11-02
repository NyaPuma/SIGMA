<?php

namespace App\Enums;

enum BudgetStatusEnum: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Return all raw enum values in a simple array (suitable for validation).
     */
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return the human-readable description for UI and reports.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('common.Pendente'),
            self::Approved => __('common.Aprovado'),
            self::Rejected => __('common.Rejeitado'),
        };
    }

    /**
     * Indicative color for badges and tables in the UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    /**
     * Indicative icon for visual representation.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if the budget has reached a terminal state (approved or rejected).
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Approved, self::Rejected => true,
            self::Pending => false,
        };
    }

    /**
     * Check if a transition to the target state is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => true,
            self::Approved, self::Rejected => false,
        };
    }

    /**
     * Safely normalize mixed input (string or enum instance).
     */
    public static function normalize(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return self::tryFrom(mb_strtolower(trim($value)));
    }
}
