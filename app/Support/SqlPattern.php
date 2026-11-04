<?php

declare(strict_types=1);

namespace App\Support;

use App\ValueObjects\SqlFragment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Portable "contains" text search.
 *
 * Raw user input must not carry LIKE wildcards, and a backslash escape only
 * works where the driver makes backslash the default escape character. SQLite
 * has no default escape character at all, so a backslash-escaped pattern
 * matches nothing there. Emitting an explicit ESCAPE clause with a quote-safe
 * character ('!') behaves identically on SQLite, MySQL and PostgreSQL without
 * any string-literal escaping differences.
 */
final class SqlPattern
{
    private const ESCAPE_CHAR = '!';

    /**
     * Adds a bracketed OR group matching any of the given columns.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $columns
     */
    public static function whereContains(Builder $query, array $columns, string $term): void
    {
        // LIKE is case-insensitive on SQLite and MySQL, but case-sensitive on PostgreSQL.
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $pattern = '%'.self::escape($term).'%';

        $conditions = [];
        $bindings = [];
        foreach ($columns as $column) {
            $conditions[] = self::assertIdentifier($column)." {$likeOperator} ? ESCAPE '".self::ESCAPE_CHAR."'";
            $bindings[] = $pattern;
        }

        $query->whereRaw(new SqlFragment('('.implode(' OR ', $conditions).')'), $bindings);
    }

    private static function escape(string $term): string
    {
        return str_replace(
            [self::ESCAPE_CHAR, '%', '_'],
            [self::ESCAPE_CHAR.self::ESCAPE_CHAR, self::ESCAPE_CHAR.'%', self::ESCAPE_CHAR.'_'],
            $term,
        );
    }

    /**
     * Column names are interpolated into raw SQL, so only plain or table-prefixed
     * identifiers are accepted.
     */
    private static function assertIdentifier(string $column): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column) !== 1) {
            throw new \InvalidArgumentException("Unsafe column identifier [{$column}].");
        }

        return $column;
    }
}
