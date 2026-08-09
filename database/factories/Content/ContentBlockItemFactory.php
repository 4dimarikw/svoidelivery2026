<?php

namespace Database\Factories\Content;


use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\ContentBlockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentBlockItem> */
class ContentBlockItemFactory extends Factory
{
    protected $model = ContentBlockItem::class;

    public function definition(): array
    {
        return [
            'content_block_id' => ContentBlock::factory(),
            'group_key' => 'activities',
            'key' => fake()->unique()->slug(),
            'title' => fake()->sentence(3),
            'content' => ['heading' => fake()->sentence()],
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
