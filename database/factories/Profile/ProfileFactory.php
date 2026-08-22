<?php

namespace Database\Factories\Profile;

use Database\Factories\UserFactory;
use Domain\Profile\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'patronymic' => fake()->optional()->firstName(),
            'phone' => fake()->optional()->phoneNumber(),
            'social_links' => fake()->optional()->passthrough(array_filter([
                'telegram' => fake()->optional()->url(),
                'vk' => fake()->optional()->url(),
            ])),
            'default_order_comment' => fake()->optional()->sentence(),
        ];
    }
}
