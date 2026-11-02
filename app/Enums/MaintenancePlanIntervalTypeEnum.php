<?php

declare(strict_types=1);

namespace App\Enums;

enum MaintenancePlanIntervalTypeEnum: string
{
    case Days = 'days';
    case UsageHours = 'usage_hours';
    case Cycles = 'cycles';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Days => __('common.Dias'),
            self::UsageHours => __('common.Horas de uso'),
            self::Cycles => __('common.Ciclos'),
        };
    }

    public static function normalize(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            return self::tryFrom(strtolower(trim($value)));
        }

        return null;
    }
}
