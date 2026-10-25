<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_it_parses_amounts_into_minor_units(string $input, int $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    public static function validAmounts(): array
    {
        return [
            'whole number' => ['1250', 125000],
            'decimal' => ['1250.5', 125050],
            'two decimals' => ['1250.05', 125005],
            'grouped' => ['1,250,000.99', 125000099],
            'spaces' => [' 42.10 ', 4210],
            'negative opening balance' => ['-75.25', -7525],
            'explicit positive' => ['+12.00', 1200],
            'zero' => ['0', 0],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_it_rejects_invalid_amounts(?string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::parse($input);
    }

    public static function invalidAmounts(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'three decimals' => ['10.001'],
            'letters' => ['ten'],
            'multiple separators' => ['1,2,3.00'],
            'lone decimal point' => ['.'],
            'too many whole digits' => ['1234567890123456'],
        ];
    }

    public function test_it_formats_minor_units_with_symbols_and_grouping(): void
    {
        $this->assertSame('Rs 1,234.56', Money::format(123456, 'PKR'));
        $this->assertSame('$ -1,234.56', Money::format(-123456, 'USD'));
        $this->assertSame('1,234.56', Money::format(123456, 'PKR', false));
    }

    public function test_it_converts_between_minor_units_and_decimal_strings(): void
    {
        $this->assertSame('1234.56', Money::toDecimal(123456));
        $this->assertSame('-0.05', Money::toDecimal(-5));
        $this->assertSame('0.00', Money::toDecimal(0));
    }

    public function test_it_formats_compact_amounts(): void
    {
        $this->assertSame('Rs 1k', Money::formatCompact(100000, 'PKR'));
        $this->assertSame('Rs 12.5k', Money::formatCompact(1250000, 'PKR'));
        $this->assertSame('Rs 1.5L', Money::formatCompact(15000000, 'PKR'));
        $this->assertSame('Rs 2Cr', Money::formatCompact(2000000000, 'PKR'));
        $this->assertSame('Rs -1k', Money::formatCompact(-100000, 'PKR'));
    }

    public function test_it_rounds_percentages_and_handles_zero_totals(): void
    {
        $this->assertSame(80, Money::percent(80000, 100000));
        $this->assertSame(67, Money::percent(2, 3));
        $this->assertSame(-50, Money::percent(-5000, 10000));
        $this->assertSame(0, Money::percent(5000, 0));
    }
}
