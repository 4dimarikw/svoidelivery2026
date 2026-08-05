<?php

namespace Database\Factories\Profile;

use Database\Factories\UserFactory;
use Domain\Profile\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),
            'label' => fake()->optional()->word(),
            'city' => fake()->city(),
            'street' => fake()->streetName(),
            'house' => (string) fake()->buildingNumber(),
            'apartment' => fake()->optional()->numerify('##'),
            'entrance' => fake()->optional()->numerify('#'),
            'floor' => fake()->optional()->numerify('#'),
            'intercom' => fake()->optional()->numerify('####'),
            'comment' => fake()->optional()->sentence(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
