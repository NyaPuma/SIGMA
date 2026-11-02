<?php

namespace App\Enums;

enum TicketStatusEnum: string
{
    case Open = 'aberta';
    case InProgress = 'em curso';
    case Closed = 'fechada';
    case Cancelled = 'cancelada';
    case PendingBudget = 'pendente orçamento';
    case Rejected = 'recusada';

    /**
     * Return all raw enum values in a simple array.
     */
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return all accepted values including unaccented variations.
     */
    /** @return array<int, string> */
    public static function acceptedValues(): array
    {
        return [
            ...self::values(),
            'pendente orcamento',
        ];
    }

    /**
     * Return the human-readable description for UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => __('common.Aberta'),
            self::InProgress => __('common.Em Curso'),
            self::Closed => __('common.Fechada'),
            self::Cancelled => __('ui.Cancelada'),
            self::PendingBudget => __('common.Pendente Orçamento'),
            self::Rejected => __('common.Recusada'),
        };
    }

    /**
     * Indicative color for badges and tables in UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InProgress => 'primary',
            self::PendingBudget => 'warning',
            self::Closed => 'success',
            self::Cancelled, self::Rejected => 'danger',
        };
    }

    /**
     * Indicative icon for visual representation.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Open => 'heroicon-o-envelope-open',
            self::InProgress => 'heroicon-o-arrow-path',
            self::PendingBudget => 'heroicon-o-banknotes',
            self::Closed => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Rejected => 'heroicon-o-no-symbol',
        };
    }

    /**
     * Check if the ticket has reached a terminal state (closed, cancelled, or rejected).
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Closed, self::Cancelled, self::Rejected => true,
            self::Open, self::InProgress, self::PendingBudget => false,
        };
    }

    /**
     * Check if the ticket is active and in processing.
     */
    public function isActive(): bool
    {
        return ! $this->isFinal();
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
            'open', 'aberta', 'aberto' => self::Open,
            'in_progress', 'em curso', 'em_curso' => self::InProgress,
            'closed', 'fechada', 'fechado' => self::Closed,
            'cancelled', 'canceled', 'cancelada', 'cancelado' => self::Cancelled,
            'pending_budget', 'pendente orçamento', 'pendente orcamento' => self::PendingBudget,
            'rejected', 'recusada', 'recusado' => self::Rejected,
            default => self::tryFrom($sanitized),
        };
    }
}
