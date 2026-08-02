<?php

namespace Database\Factories\Catalog;

use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_uuid' => fake()->unique()->uuid(),
            'external_code' => fake()->unique()->bothify('##-#####??'),
            'article' => fake()->unique()->bothify('ARTICLE-####'),
            'name' => fake()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'category_id' => Category::factory(),
            'manufacturer_id' => null,
            'volume_id' => null,
            'container_id' => null,
            'price' => fake()->randomFloat(2, 50, 5000),
            'stock_quantity' => fake()->numberBetween(0, 500),
            'in_stock' => true,
            'package_units' => fake()->optional()->numberBetween(6, 24),
            'packaging_raw' => null,
            'source_category_path' => null,
            'shelf_life_days' => fake()->optional()->numberBetween(30, 730),
            'brand' => fake()->optional()->company(),
            'sales_rating' => null,
            'status' => ProductStatus::PUBLISHED,
            'synced_at' => now(),
        ];
    }
}
