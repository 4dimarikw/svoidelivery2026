<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Support\ValueObjects\Price;
use Tests\TestCase;

class StrictPriceTest extends TestCase
{
    public static function validAmounts(): array
    {
        return [['12.50', 1250], ['12,50', 1250], ['12.5', 1250], ['12', 1200], [12.5, 1250],
            ['0', 0], [' 0012,50 ', 1250], ['1 234,56', 123456], ["1\u{00A0}234.56", 123456],
            ["1\u{202F}234,56", 123456], ['9 999 999 999,99', Price::MAX_MINOR], [0.01, 1]];
    }

    #[DataProvider('validAmounts')]
    public function test_valid_values_round_trip_exactly(int|float|string $input, int $minor): void
    {
        $price = Price::fromMajor($input);
        $this->assertSame($minor, $price->minor());
        $this->assertSame($minor, Price::fromMajor($price->decimal())->minor());
    }

    public static function invalidAmounts(): array
    {
        return array_map(fn ($value) => [$value], ['', 'abc', '-1', '+1', '1e2', '1E-2', '1.234', '12,500',
            '1,2.3', '1,234.56', '12 34', '1 23 456', '.50', '12.', '10000000000', '9999999999.999',
            '9999999999999999999999999999', INF, NAN, 12.345, 0.1 + 0.2]);
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_values_are_never_coerced_or_rounded(float|string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        Price::fromMajor($input);
    }

    public function test_integer_overflow_in_arithmetic_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Price::fromMinor(PHP_INT_MAX)->multiply(2);
    }
}
