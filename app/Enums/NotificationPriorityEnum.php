<?php

namespace App\Enums;

enum NotificationPriorityEnum: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * Return all raw enum values in a simple array.
     */
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return the human-readable description for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => __('tickets.Baixa'),
            self::Normal => __('common.Normal'),
            self::High => __('tickets.Alta'),
            self::Urgent => __('tickets.Urgente'),
        };
    }

    /**
     * Indicative color for badges, toasts, and alerts.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'info',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }

    /**
     * Indicative icon for visual representation.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-o-arrow-down',
            self::Normal => 'heroicon-o-minus',
            self::High => 'heroicon-o-arrow-up',
            self::Urgent => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Numeric weight for sorting by priority.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Normal => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }

    /**
     * Check if the notification requires immediate dispatch.
     */
    public function isHighPriority(): bool
    {
        return match ($this) {
            self::High, self::Urgent => true,
            self::Low, self::Normal => false,
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
