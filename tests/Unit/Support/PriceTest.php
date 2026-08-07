<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use InvalidArgumentException;
use Support\ValueObjects\Price;
use Tests\TestCase;

class PriceTest extends TestCase
{
    public function test_from_major_converts_rubles_to_minor_units(): void
    {
        $price = Price::fromMajor('420.50');

        $this->assertSame(42050, $price->minor());
        $this->assertSame(420.5, $price->major());
    }

    public function test_from_minor_formats_with_symbol_before_the_amount(): void
    {
        $price = Price::fromMinor(42050);

        $this->assertSame('₽ 421', $price->format());
        $this->assertSame('₽ 420,50', $price->format(2));
    }

    public function test_to_string_matches_default_format(): void
    {
        $price = Price::fromMajor(1234);

        $this->assertSame('₽ 1 234', (string) $price);
    }

    public function test_multiply_scales_the_minor_amount(): void
    {
        $price = Price::fromMajor(10)->multiply(3);

        $this->assertSame(3000, $price->minor());
    }

    public function test_add_sums_two_prices_in_the_same_currency(): void
    {
        $sum = Price::fromMajor(10)->add(Price::fromMajor(5));

        $this->assertSame(1500, $sum->minor());
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Price::fromMajor(-1);
    }

    public function test_unknown_currency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Price::fromMinor(100, 'USD');
    }

    public function test_json_serializes_to_the_major_amount(): void
    {
        $price = Price::fromMajor(420.5);

        $this->assertSame(420.5, $price->jsonSerialize());
    }

    public function test_is_zero(): void
    {
        $this->assertTrue(Price::fromMinor(0)->isZero());
        $this->assertFalse(Price::fromMajor(0.01)->isZero());
    }
}
