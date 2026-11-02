<?php

declare(strict_types=1);

namespace App\Enums;

enum PartUnitOfMeasureEnum: string
{
    case Unit = 'unit';
    case Meter = 'meter';
    case Liter = 'liter';
    case Kg = 'kg';
    case Pair = 'pair';
    case Set = 'set';
    case Roll = 'roll';
    case Other = 'other';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Unit => __('common.Unidade'),
            self::Meter => __('common.Metro'),
            self::Liter => __('common.Litro'),
            self::Kg => __('common.Quilograma (kg)'),
            self::Pair => __('common.Par'),
            self::Set => __('common.Kit / Conjunto'),
            self::Roll => __('common.Rolo'),
            self::Other => __('common.Outro'),
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
