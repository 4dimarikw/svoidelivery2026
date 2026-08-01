<?php

namespace Database\Factories\Catalog;

use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UntappdBeer>
 */
class UntappdBeerFactory extends Factory
{
    protected $model = UntappdBeer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'beer_id' => fake()->unique()->numberBetween(1000000, 9999999),
            'name' => fake()->words(3, true),
            'brewery' => fake()->company(),
            'style' => fake()->word(),
            'rating_count' => fake()->numberBetween(0, 5000),
            'rating_score' => fake()->randomFloat(2, 1, 5),
            'label' => null,
            'url' => fake()->url(),
            'synced_at' => now(),
        ];
    }
}
