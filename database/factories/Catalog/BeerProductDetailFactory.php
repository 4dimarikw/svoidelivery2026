<?php

namespace Database\Factories\Catalog;

use Domain\Catalog\Models\BeerProductDetail;
use Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BeerProductDetail>
 */
class BeerProductDetailFactory extends Factory
{
    protected $model = BeerProductDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'beer_style_id' => null,
            'untappd_beer_id' => null,
            'abv' => fake()->randomFloat(2, 3, 12),
            'ibu' => fake()->randomFloat(2, 5, 100),
            'plato' => fake()->randomFloat(2, 8, 20),
            'ebc' => fake()->randomFloat(2, 4, 80),
        ];
    }
}
