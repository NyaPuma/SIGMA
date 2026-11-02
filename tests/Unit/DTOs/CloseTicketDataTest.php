<?php

namespace Tests\Unit\DTOs;

use App\DTOs\CloseTicketData;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloseTicketDataTest extends TestCase
{
    #[Test]
    public function it_creates_dto_from_constructor(): void
    {
        $dto = new CloseTicketData(actualCost: 45.5, report: 'Resolvido', force: true);

        $this->assertEquals(45.5, $dto->actualCost);
        $this->assertEquals('Resolvido', $dto->report);
        $this->assertTrue($dto->force);
    }

    #[Test]
    public function it_creates_dto_from_request(): void
    {
        $dto = CloseTicketData::fromRequest([
            'actual_cost' => '78.90',
            'report' => '  Substituída a fonte de alimentação.  ',
            'force' => 'true',
        ]);

        $this->assertEquals(78.9, $dto->actualCost);
        $this->assertEquals('Substituída a fonte de alimentação.', $dto->report);
        $this->assertTrue($dto->force);
    }

    #[Test]
    public function it_normalizes_empty_report_to_null_and_force_to_bool(): void
    {
        $dto = CloseTicketData::fromRequest(['actual_cost' => 10, 'report' => '   ', 'force' => '1']);

        $this->assertNull($dto->report);
        $this->assertTrue($dto->force);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $dto = CloseTicketData::fromRequest(['actual_cost' => 25.5, 'force' => false]);

        $this->assertEquals([
            'actual_cost' => 25.5,
            'report' => null,
            'force' => false,
        ], $dto->toArray());
    }

    #[Test]
    public function it_parses_currency_symbols_and_thousands_separators(): void
    {
        $this->assertEquals(1500.5, CloseTicketData::fromRequest(['actual_cost' => '1.500,50 €'])->actualCost);
        $this->assertEquals(1500.5, CloseTicketData::fromRequest(['actual_cost' => '1,500.50'])->actualCost);
        $this->assertEquals(150.5, CloseTicketData::fromRequest(['actual_cost' => '150,50'])->actualCost);
    }

    #[Test]
    public function it_rejects_negative_costs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CloseTicketData(actualCost: -5);
    }
}
