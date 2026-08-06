<?php

declare(strict_types=1);

namespace Database\Factories\Favorite;

use Database\Factories\Catalog\ProductFactory;
use Database\Factories\UserFactory;
use Domain\Favorite\Models\Favorite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorite>
 */
class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),
            'product_id' => ProductFactory::new(),
        ];
    }
}
