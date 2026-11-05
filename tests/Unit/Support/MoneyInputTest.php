<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MoneyInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyInputTest extends TestCase
{
    #[Test]
    #[DataProvider('monetaryStrings')]
    public function it_parses_a_monetary_string(mixed $input, float $expected): void
    {
        $this->assertSame($expected, MoneyInput::parse($input));
    }

    /**
     * @return array<string, array{mixed, float}>
     */
    public static function monetaryStrings(): array
    {
        return [
            'plain digits' => ['1500', 1500.0],
            'portuguese decimal' => ['0,50', 0.5],
            'english decimal' => ['0.50', 0.5],
            'portuguese grouping and decimal' => ['1.500,50', 1500.5],
            'portuguese grouping with currency symbol' => ['1.500,50 €', 1500.5],
            'english grouping and decimal' => ['1,500.50', 1500.5],
            'negative portuguese' => ['-1.234,56', -1234.56],
            'portuguese two groups' => ['1.500.000', 1500000.0],
            'english two groups' => ['12,345,678', 12345678.0],
            'three decimal places round' => ['150.505', 150.51],
            'empty string' => ['', 0.0],
            'no digits' => ['abc', 0.0],
            'null' => [null, 0.0],
            'integer' => [7, 7.0],
            'float' => [7.5, 7.5],
        ];
    }

    #[Test]
    public function it_does_not_zero_a_grouped_amount(): void
    {
        // "1.500.000" is not numeric, so reading its separators as decimal points made
        // the parser answer 0.0 and the budget or cost was silently lost.
        $this->assertSame(1500000.0, MoneyInput::parse('1.500.000'));
        $this->assertSame(1500000.0, MoneyInput::parse('1.500.000,00'));
    }
}
