<?php

declare(strict_types=1);

namespace Database\Factories\Vk;

use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VkPost>
 */
class VkPostFactory extends Factory
{
    protected $model = VkPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => -1,
            'vk_post_id' => fake()->unique()->numberBetween(1, 1000000),
            'post_type' => 'post',
            'posted_at' => now(),
            'text' => fake()->sentence(),
            'message_text' => null,
            'images' => [],
            'rejected_images' => null,
            'raw' => [],
            'status' => VkPostStatus::DRAFT,
            'broadcast_at' => null,
            'broadcast_stats' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VkPostStatus::READY,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VkPostStatus::SENT,
            'broadcast_at' => now(),
            'broadcast_stats' => ['sent' => 1, 'failed' => 0],
        ]);
    }
}
