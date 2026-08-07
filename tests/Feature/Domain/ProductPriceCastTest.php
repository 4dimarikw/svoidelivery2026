<?php

namespace Tests\Feature\Domain;

use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Support\ValueObjects\Price;
use Tests\TestCase;

class ProductPriceCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_is_stored_as_rubles_and_cast_to_a_value_object(): void
    {
        // products.price — decimal(12,2) в рублях; PriceCast конвертирует
        // в Support\ValueObjects\Price (копейки внутри) и обратно.
        $product = Product::factory()->create(['price' => 1234.56]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => '1234.56',
        ]);

        $fresh = $product->fresh();

        $this->assertInstanceOf(Price::class, $fresh->price);
        $this->assertSame(123456, $fresh->price->minor());
        $this->assertSame(1234.56, $fresh->price->major());
    }

    public function test_price_can_be_assigned_as_a_value_object(): void
    {
        $product = Product::factory()->create(['price' => 100]);

        $product->price = Price::fromMajor(99.9);
        $product->save();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => '99.90',
        ]);
        $this->assertSame(9990, $product->fresh()->price->minor());
    }
}
