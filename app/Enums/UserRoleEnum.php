<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case User = 'user';
    case Technician = 'technician';
    case Admin = 'admin';

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
            self::User => __('common.Utilizador'),
            self::Technician => __('common.Técnico'),
            self::Admin => __('common.Administrador'),
        };
    }

    /**
     * Indicative color for badges and profile elements.
     */
    public function color(): string
    {
        return match ($this) {
            self::User => 'gray',
            self::Technician => 'info',
            self::Admin => 'purple',
        };
    }

    /**
     * Indicative icon for visual representation.
     */
    public function icon(): string
    {
        return match ($this) {
            self::User => 'heroicon-o-user',
            self::Technician => 'heroicon-o-wrench-screwdriver',
            self::Admin => 'heroicon-o-shield-check',
        };
    }

    /**
     * Numeric weight for role hierarchy verification.
     */
    public function weight(): int
    {
        return match ($this) {
            self::User => 1,
            self::Technician => 2,
            self::Admin => 3,
        };
    }

    /**
     * Check if the role has Administrator privileges.
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Check if the role has Technician privileges.
     */
    public function isTechnician(): bool
    {
        return $this === self::Technician;
    }

    /**
     * Check if the role is a regular User.
     */
    public function isUser(): bool
    {
        return $this === self::User;
    }

    /**
     * Check if the user has elevated privileges (Technician or Admin).
     */
    public function hasElevatedPrivileges(): bool
    {
        return match ($this) {
            self::Technician, self::Admin => true,
            self::User => false,
        };
    }

    /**
     * Check if the role has at least the required role in hierarchy.
     */
    public function hasAtLeastRole(self $requiredRole): bool
    {
        return $this->weight() >= $requiredRole->weight();
    }

    /**
     * Safely normalize generic input (string, PT synonyms, or enum instance).
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
            'admin', 'administrador' => self::Admin,
            'technician', 'técnico', 'tecnico' => self::Technician,
            'user', 'utilizador', 'usuário', 'usuario' => self::User,
            default => self::tryFrom($sanitized),
        };
    }
}
