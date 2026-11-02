<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parses user-submitted monetary strings into a rounded float.
 *
 * Accepts both Portuguese ("1.500,50 €") and English ("1,500.50") notation:
 * the rightmost separator wins when both ',' and '.' are present.
 */
final class MoneyInput
{
    public static function parse(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        if (! is_string($value)) {
            return 0.0;
        }

        $cleaned = preg_replace('/[^\d,.\-]/', '', $value) ?? '';

        if ($cleaned === '' || $cleaned === '-') {
            return 0.0;
        }

        $lastComma = strrpos($cleaned, ',');
        $lastDot = strrpos($cleaned, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $cleaned = $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $cleaned))
                : str_replace(',', '', $cleaned);
        } elseif ($lastComma !== false) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return is_numeric($cleaned) ? round((float) $cleaned, 2) : 0.0;
    }
}
