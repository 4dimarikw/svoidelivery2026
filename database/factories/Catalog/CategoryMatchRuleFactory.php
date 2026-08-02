<?php

namespace Database\Factories\Catalog;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\CategoryMatchRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryMatchRule>
 */
class CategoryMatchRuleFactory extends Factory
{
    protected $model = CategoryMatchRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'type' => CategoryMatchType::Contains,
            'match_when' => null,
            'value' => fake()->unique()->word(),
            'priority' => 0,
            'is_active' => true,
        ];
    }
}
