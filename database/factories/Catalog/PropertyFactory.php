<?php

namespace Database\Factories\Catalog;

use Domain\Catalog\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->word();

        return [
            'code' => $code,
            'name' => ucfirst($code),
            'data_type' => 'string',
            'unit' => null,
            'storage_table' => 'products',
            'storage_column' => $code,
        ];
    }
}
