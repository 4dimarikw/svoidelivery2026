<?php

declare(strict_types=1);

namespace Database\Factories\Logging;

use Domain\Auth\Models\User;
use Domain\Logging\Models\EventLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventLog>
 *
 * Только create() — EventLog::booted() бросает исключение на updating(),
 * значит state()/afterCreating(), меняющие запись через update(), недопустимы.
 */
class EventLogFactory extends Factory
{
    protected $model = EventLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_type' => fake()->word(),
            'level' => 'info',
            'message' => fake()->sentence(),
            'context' => [],
            'caused_by_user_id' => null,
            'caused_by_type' => 'system',
            'created_at' => now(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'caused_by_user_id' => $user->id,
            'caused_by_type' => 'user',
        ]);
    }

    public function console(): static
    {
        return $this->state(fn (array $attributes) => [
            'caused_by_user_id' => null,
            'caused_by_type' => 'console',
        ]);
    }
}
