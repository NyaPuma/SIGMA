<?php

namespace App\Enums;

enum AuditEventEnum: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Login = 'login';
    case Logout = 'logout';
    case PasswordChanged = 'password_changed';

    /**
     * Return all raw enum values in a simple array.
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
            self::Created => __('auth.Registo Criado'),
            self::Updated => __('auth.Registo Atualizado'),
            self::Deleted => __('auth.Registo Eliminado'),
            self::Login => __('auth.Início de Sessão'),
            self::Logout => __('auth.Fim de Sessão'),
            self::PasswordChanged => __('auth.Palavra-passe Alterada'),
        };
    }

    /**
     * Indicative color for badges and tables in UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Created => 'success',
            self::Updated => 'info',
            self::Deleted => 'danger',
            self::Login, self::Logout => 'gray',
            self::PasswordChanged => 'warning',
        };
    }

    /**
     * Check if the event belongs to authentication/security actions.
     */
    public function isAuthEvent(): bool
    {
        return match ($this) {
            self::Login, self::Logout, self::PasswordChanged => true,
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
