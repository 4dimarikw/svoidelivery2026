<?php

namespace Database\Factories\Content;


use Domain\Content\Models\SiteSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteSection> */
class SiteSectionFactory extends Factory
{
    protected $model = SiteSection::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'route_name' => 'home',
            'fragment' => fake()->unique()->slug(),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
