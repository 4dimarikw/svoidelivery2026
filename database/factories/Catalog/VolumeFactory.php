<?php

namespace Database\Factories\Catalog;

use Domain\Catalog\Models\Volume;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Volume>
 */
class VolumeFactory extends Factory
{
    protected $model = Volume::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $milliliters = fake()->unique()->randomElement([250, 330, 450, 500, 750, 20000, 30000]);

        return [
            'milliliters' => $milliliters,
            'label' => $milliliters >= 1000
                ? number_format($milliliters / 1000, 0).' л'
                : number_format($milliliters / 1000, 2).' л',
        ];
    }
}
