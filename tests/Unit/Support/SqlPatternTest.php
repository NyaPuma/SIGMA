<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Equipment;
use App\Support\SqlPattern;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

final class SqlPatternTest extends FeatureTestCase
{
    #[Test]
    public function it_emits_an_explicit_escape_clause_for_every_column(): void
    {
        $query = Equipment::query();
        SqlPattern::whereContains($query, ['name', 'serial'], 'impressora');

        $this->assertStringContainsString(
            "(name like ? ESCAPE '!' OR serial like ? ESCAPE '!')",
            $query->toSql()
        );
        $this->assertSame(['%impressora%', '%impressora%'], $query->getBindings());
    }

    #[Test]
    public function it_escapes_wildcards_and_the_escape_character_in_the_term(): void
    {
        $query = Equipment::query();
        SqlPattern::whereContains($query, ['name'], '50%!_x');

        $this->assertSame(['%50!%!!!_x%'], $query->getBindings());
    }

    #[Test]
    public function it_matches_rows_whose_value_contains_a_literal_percent(): void
    {
        Equipment::factory()->create(['name' => 'Álcool 70% iso']);
        Equipment::factory()->create(['name' => 'Detergente neutro']);

        $query = Equipment::query();
        SqlPattern::whereContains($query, ['name'], '70%');

        $this->assertSame(['Álcool 70% iso'], $query->pluck('name')->all());
    }

    #[Test]
    public function it_does_not_treat_an_underscore_as_a_single_character_wildcard(): void
    {
        Equipment::factory()->create(['name' => 'Sala B_2']);
        Equipment::factory()->create(['name' => 'Sala BX2']);

        $query = Equipment::query();
        SqlPattern::whereContains($query, ['name'], 'B_2');

        $this->assertSame(['Sala B_2'], $query->pluck('name')->all());
    }

    #[Test]
    public function it_rejects_column_names_that_are_not_plain_identifiers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SqlPattern::whereContains(Equipment::query(), ['name); DROP TABLE equipment; --'], 'x');
    }
}
