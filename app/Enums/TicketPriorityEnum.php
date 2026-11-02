<?php

namespace App\Enums;

enum TicketPriorityEnum: string
{
    case Low = 'baixa';
    case Medium = 'média';
    case High = 'alta';
    case Critical = 'crítica';

    /**
     * Return all raw enum values in a simple array.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return all accepted values including unaccented variations.
     *
     * @return array<int, string>
     */
    public static function acceptedValues(): array
    {
        return [
            ...self::values(),
            'media',
            'critica',
        ];
    }

    /**
     * Return the human-readable description for UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => __('tickets.Baixa'),
            self::Medium => __('common.Média'),
            self::High => __('tickets.Alta'),
            self::Critical => __('common.Crítica'),
        };
    }

    /**
     * Indicative color for badges and tables in UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    /**
     * Indicative icon for visual representation.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-o-arrow-down-short',
            self::Medium => 'heroicon-o-minus',
            self::High => 'heroicon-o-arrow-up-short',
            self::Critical => 'heroicon-o-fire',
        };
    }

    /**
     * Numeric weight for sorting by priority.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    /**
     * SLA limit for response/resolution (in hours).
     */
    public function slaHours(): int
    {
        return match ($this) {
            self::Low => 48,
            self::Medium => 24,
            self::High => 8,
            self::Critical => 2,
        };
    }

    /**
     * Check if the ticket requires immediate escalation.
     */
    public function requiresImmediateAttention(): bool
    {
        return match ($this) {
            self::High, self::Critical => true,
            self::Low, self::Medium => false,
        };
    }

    /**
     * Safely normalize generic input (string or enum instance).
     */
    public static function normalize(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $sanitized = mb_strtolower(trim($value));

        return match ($sanitized) {
            'low', 'baixa' => self::Low,
            'medium', 'média', 'media' => self::Medium,
            'high', 'alta' => self::High,
            'critical', 'urgent', 'crítica', 'critica' => self::Critical,
            default => self::tryFrom($sanitized),
        };
    }
}
