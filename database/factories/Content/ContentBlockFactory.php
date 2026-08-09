<?php

namespace Database\Factories\Content;


use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\SiteSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentBlock> */
class ContentBlockFactory extends Factory
{
    protected $model = ContentBlock::class;

    public function definition(): array
    {
        return [
            'site_section_id' => SiteSection::factory(),
            'key' => fake()->unique()->slug(),
            'type' => 'program',
            'title' => fake()->sentence(3),
            'content' => ['heading' => fake()->sentence()],
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
