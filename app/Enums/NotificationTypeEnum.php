<?php

namespace App\Enums;

enum NotificationTypeEnum: string
{
    case BudgetRequest = 'budget_request';
    case BudgetSubmitted = 'budget_submitted';
    case BudgetApproved = 'budget_approved';
    case BudgetRejected = 'budget_rejected';
    case BudgetAutoApproved = 'budget_auto_approved';
    case TicketClosed = 'ticket_closed';
    case TicketCreated = 'ticket_created';
    case PriorityOverride = 'priority_override';
    case LowStock = 'low_stock';

    /**
     * Return all raw enum values in a simple array.
     */
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return the human-readable description for UI and emails.
     */
    public function label(): string
    {
        return match ($this) {
            self::BudgetRequest => __('common.Pedido de Orçamento'),
            self::BudgetSubmitted => __('common.Orçamento Submetido'),
            self::BudgetApproved => __('common.Orçamento Aprovado'),
            self::BudgetRejected => __('common.Orçamento Rejeitado'),
            self::BudgetAutoApproved => __('common.Orçamento Aprovado Automaticamente'),
            self::TicketClosed => __('tickets.Ticket Encerrado'),
            self::TicketCreated => __('tickets.Novo Ticket Criado'),
            self::PriorityOverride => __('common.Alteração Manual de Prioridade'),
            self::LowStock => __('stock.Stock Baixo'),
        };
    }

    /**
     * Icon identifier for the notification type (Heroicons outline naming).
     */
    public function icon(): string
    {
        return match ($this) {
            self::BudgetRequest => 'heroicon-o-banknotes',
            self::BudgetSubmitted => 'heroicon-o-clipboard-document-list',
            self::BudgetApproved, self::BudgetAutoApproved => 'heroicon-o-check-circle',
            self::BudgetRejected => 'heroicon-o-x-circle',
            self::TicketClosed => 'heroicon-o-wrench-screwdriver',
            self::TicketCreated => 'heroicon-o-ticket',
            self::PriorityOverride => 'heroicon-o-exclamation-triangle',
            self::LowStock => 'heroicon-o-archive-box',
        };
    }

    /**
     * Indicative color for badges and toasts.
     */
    public function color(): string
    {
        return match ($this) {
            self::BudgetApproved, self::BudgetAutoApproved => 'success',
            self::BudgetRejected, self::PriorityOverride => 'danger',
            self::BudgetRequest, self::BudgetSubmitted => 'warning',
            self::TicketCreated, self::TicketClosed => 'info',
            self::LowStock => 'warning',
        };
    }

    /**
     * Map default priority for each notification type.
     */
    public function defaultPriority(): NotificationPriorityEnum
    {
        return match ($this) {
            self::PriorityOverride, self::BudgetRejected => NotificationPriorityEnum::Urgent,
            self::BudgetRequest, self::BudgetSubmitted => NotificationPriorityEnum::High,
            self::BudgetApproved, self::BudgetAutoApproved, self::TicketCreated => NotificationPriorityEnum::Normal,
            self::TicketClosed => NotificationPriorityEnum::Low,
            self::LowStock => NotificationPriorityEnum::High,
        };
    }

    /**
     * Check if the notification type belongs to the budget workflow.
     */
    public function isBudgetRelated(): bool
    {
        return match ($this) {
            self::BudgetRequest,
            self::BudgetSubmitted,
            self::BudgetApproved,
            self::BudgetRejected,
            self::BudgetAutoApproved => true,
            default => false,
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
