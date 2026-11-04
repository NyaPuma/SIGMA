<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Driver-specific date SQL shared by the analytics queries.
 *
 * SQLite, MySQL and PostgreSQL have no common function for "format a timestamp
 * as a month key" or "difference between two timestamps in minutes", so every
 * grouped-by-month query resolves its expression here instead of branching on
 * the driver inline.
 *
 * The arguments are column names written in code, never request input.
 */
final class DateSql
{
    /**
     * Expression that renders a timestamp column as a `YYYY-MM` string.
     */
    public static function month(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * Expression that returns the whole minutes between two timestamp columns.
     */
    public static function minutesBetween(string $from, string $to): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "(julianday({$to}) - julianday({$from})) * 1440",
            'pgsql' => "EXTRACT(EPOCH FROM ({$to} - {$from})) / 60",
            default => "TIMESTAMPDIFF(MINUTE, {$from}, {$to})",
        };
    }
}
