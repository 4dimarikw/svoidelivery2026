<?php

namespace Database\Factories\Catalog;

use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\ProductBarcode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBarcode>
 */
class ProductBarcodeFactory extends Factory
{
    protected $model = ProductBarcode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'barcode' => fake()->unique()->ean13(),
        ];
    }
}
