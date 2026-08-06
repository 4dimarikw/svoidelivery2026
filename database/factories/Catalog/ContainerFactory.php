<?php

namespace Database\Factories\Catalog;

use Domain\Catalog\Models\Container;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Container>
 */
class ContainerFactory extends Factory
{
    protected $model = Container::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->randomElement(['can', 'glass_bottle', 'pet_keg']);

        return [
            'code' => $code,
            'name' => match ($code) {
                'can' => 'Алюминиевая банка',
                'glass_bottle' => 'Стеклянная бутылка',
                'pet_keg' => 'ПЭТ-кег',
            },
            'label' => match ($code) {
                'can' => 'Жестяная банка',
                'glass_bottle' => 'Стеклянная бутылка',
                'pet_keg' => 'ПЭТ кег',
            },
            'is_active' => true,
        ];
    }
}
