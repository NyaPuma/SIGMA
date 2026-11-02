<?php

namespace App\Enums;

enum TicketWorkflowStatusEnum: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingBudget = 'waiting_budget';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * Return all raw enum values in a simple array.
     */
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return the human-readable description for UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => __('tickets.Aberto'),
            self::InProgress => __('common.Em Curso'),
            self::WaitingBudget => __('common.Pendente de Orçamento'),
            self::Approved => __('common.Aprovado'),
            self::Rejected => __('common.Recusado'),
            self::Closed => __('tickets.Fechado'),
            self::Cancelled => __('tickets.Cancelado'),
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
            self::WaitingBudget => 'warning',
            self::Approved => 'success',
            self::Rejected, self::Cancelled => 'danger',
            self::Closed => 'gray',
        };
    }

    /**
     * Indicative icon for visual representation.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Open => 'heroicon-o-sparkles',
            self::InProgress => 'heroicon-o-play',
            self::WaitingBudget => 'heroicon-o-clock',
            self::Approved => 'heroicon-o-check-circle',
            self::Rejected => 'heroicon-o-x-circle',
            self::Closed => 'heroicon-o-archive-box',
            self::Cancelled => 'heroicon-o-ban',
        };
    }

    /**
     * Check if the status is terminal.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Closed, self::Cancelled, self::Rejected => true,
            self::Open, self::InProgress, self::WaitingBudget, self::Approved => false,
        };
    }

    /**
     * Check if the ticket is active within the workflow.
     */
    public function isActive(): bool
    {
        return ! $this->isFinal();
    }

    /**
     * Define valid state transitions from current status.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::WaitingBudget, self::Cancelled],
            self::InProgress => [self::WaitingBudget, self::Approved, self::Closed, self::Cancelled],
            self::WaitingBudget => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::InProgress, self::Closed, self::Cancelled],
            self::Rejected => [self::WaitingBudget, self::Cancelled],
            self::Closed, self::Cancelled => [],
        };
    }

    /**
     * Check if a transition to the target status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
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

        return self::tryFrom(mb_strtolower(trim($value)));
    }
}
