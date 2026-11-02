<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementTypeEnum: string
{
    case In = 'in';
    case Out = 'out';
    case Adjust = 'adjust';
    case Return = 'return';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::In => __('common.Entrada'),
            self::Out => __('common.Saída'),
            self::Adjust => __('common.Ajuste'),
            self::Return => __('common.Devolução'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'danger',
            self::Adjust => 'warning',
            self::Return => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::In => 'heroicon-o-arrow-down-tray',
            self::Out => 'heroicon-o-arrow-up-tray',
            self::Adjust => 'heroicon-o-wrench-screwdriver',
            self::Return => 'heroicon-o-arrow-uturn-left',
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
