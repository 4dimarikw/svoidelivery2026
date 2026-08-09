<?php

namespace Database\Factories\Content;


use Domain\Content\Models\SiteMenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteMenu> */
class SiteMenuFactory extends Factory
{
    protected $model = SiteMenu::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(),
            'title' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
