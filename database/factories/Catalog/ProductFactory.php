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
            // В реальных данных brand заполнен у всех товаров (1С всегда
            // присылает "Марку") — optional() здесь раньше делал тесты,
            // которые assertSee($product->name) без явного brand, случайно
            // флаковыми: карточка показывает brand вместо name (см.
            // product-card.blade.php), а optional() иногда подставлял
            // случайную компанию, пряча name с экрана.
            'brand' => fake()->company(),
            'status' => ProductStatus::PUBLISHED,
        ];
    }
}
