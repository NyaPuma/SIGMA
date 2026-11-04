<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Grammar;

/**
 * Immutable Value Object holding a SQL fragment chosen at runtime (usually by
 * database driver). Laravel 13 types its own Expression as
 * `@template TValue of literal-string`, so fragments built by interpolation
 * cannot be handed to select()/groupBy()/whereRaw() without lying about being
 * a compile-time literal. The grammars test the contract, not the concrete
 * class, so this implementation is accepted everywhere Laravel accepts a raw
 * expression.
 */
final readonly class SqlFragment implements ExpressionContract
{
    public function __construct(private string $sql) {}

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }
}
