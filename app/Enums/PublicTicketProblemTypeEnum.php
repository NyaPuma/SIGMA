<?php

namespace App\Enums;

enum PublicTicketProblemTypeEnum: string
{
    case Breakdown = 'avaria';
    case Preventive = 'manutencao_preventiva';
    case Consumables = 'falta_consumiveis';
    case Other = 'outro';

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
            self::Breakdown => __('common.Avaria'),
            self::Preventive => __('maintenance_plan.Manutenção Preventiva'),
            self::Consumables => __('common.Falta de Consumíveis'),
            self::Other => __('common.Outro'),
        };
    }

    /**
     * Map problem type to corresponding ticket priority.
     */
    public function priority(): TicketPriorityEnum
    {
        return match ($this) {
            self::Breakdown => TicketPriorityEnum::High,
            self::Preventive => TicketPriorityEnum::Low,
            self::Consumables => TicketPriorityEnum::Medium,
            self::Other => TicketPriorityEnum::Medium,
        };
    }

    /**
     * Icon identifier for the problem type (Heroicons outline naming).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Breakdown => 'heroicon-o-wrench-screwdriver',
            self::Preventive => 'heroicon-o-shield-check',
            self::Consumables => 'heroicon-o-shopping-cart',
            self::Other => 'heroicon-o-document-text',
        };
    }

    /**
     * Safely normalize generic input (handles accents, aliases, and case).
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
            'breakdown', 'avaria' => self::Breakdown,
            'preventive', 'manutencao_preventiva', 'manutenção preventiva', 'manutencao preventiva' => self::Preventive,
            'consumables', 'falta_consumiveis', 'falta de consumíveis', 'falta de consumiveis' => self::Consumables,
            'other', 'outro' => self::Other,
            default => self::tryFrom($sanitized),
        };
    }
}
