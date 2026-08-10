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
            'address' => fake()->streetAddress(),
            'comment' => fake()->optional()->sentence(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
