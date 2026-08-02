<?php

namespace Database\Factories\Catalog;

use Domain\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => Str::slug($name, '_'),
            'name' => ucfirst($name),
            'is_active' => true,
            'expects_container' => false,
            'expects_volume' => false,
            'price_exempt' => false,
            'name_from_article' => false,
            'default_brand' => null,
            'container_code' => null,
        ];
    }
}
