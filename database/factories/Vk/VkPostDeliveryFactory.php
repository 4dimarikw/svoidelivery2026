<?php

declare(strict_types=1);

namespace Database\Factories\Vk;

use Database\Factories\UserFactory;
use Domain\Vk\Models\VkPostDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VkPostDelivery>
 */
class VkPostDeliveryFactory extends Factory
{
    protected $model = VkPostDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vk_post_id' => VkPostFactory::new(),
            'user_id' => UserFactory::new(),
            'status' => 'sent',
            'error' => null,
            'sent_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error' => fake()->sentence(),
            'sent_at' => null,
        ]);
    }
}
