<?php

namespace Database\Factories\Catalog;

use Domain\Catalog\Models\BeerStyle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BeerStyle>
 */
class BeerStyleFactory extends Factory
{
    protected $model = BeerStyle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => ucfirst($name),
            'normalized_name' => Str::lower($name),
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}
