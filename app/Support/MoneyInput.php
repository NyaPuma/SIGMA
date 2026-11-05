<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parses user-submitted monetary strings into a rounded float.
 *
 * Accepts both Portuguese ("1.500,50 €") and English ("1,500.50") notation:
 * the rightmost separator wins when both ',' and '.' are present, and a
 * separator that appears more than once is a thousands group ("1.500.000"
 * and "1,234,567" are 1500000 and 1234567).
 *
 * A single separator followed by three digits stays ambiguous ("1.500" is
 * 1500 in pt-PT and 1.5 in en) and is read as the decimal separator, which is
 * what the HTML number inputs and the API clients send.
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
        } elseif ($lastComma !== false || $lastDot !== false) {
            $separator = $lastComma === false ? '.' : ',';

            // Repeated separator: grouping, never a decimal point. Left as-is otherwise
            // `is_numeric` below would reject "1.500.000" and the amount would become 0.
            if (substr_count($cleaned, $separator) > 1) {
                $cleaned = str_replace($separator, '', $cleaned);
            } elseif ($separator === ',') {
                $cleaned = str_replace(',', '.', $cleaned);
            }
        }

        return is_numeric($cleaned) ? round((float) $cleaned, 2) : 0.0;
    }
}
