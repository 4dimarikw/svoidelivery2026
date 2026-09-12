<?php

namespace Database\Factories\Content;

use ContentCms\Models\SiteMenu;
use ContentCms\Models\SiteMenuItem;
use ContentCms\Models\SiteSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteMenuItem> */
class SiteMenuItemFactory extends Factory
{
    protected $model = SiteMenuItem::class;

    public function definition(): array
    {
        return [
            'site_menu_id' => SiteMenu::factory(),
            'key' => fake()->unique()->bothify('item-????-####'),
            'site_section_id' => SiteSection::factory(),
            'external_url' => null,
            'label' => fake()->words(2, true),
            'open_in_new_tab' => false,
            'is_active' => true,
        ];
    }

    public function external(): static
    {
        return $this->state(fn (): array => [
            'site_section_id' => null,
            'external_url' => fake()->url(),
        ]);
    }
}
