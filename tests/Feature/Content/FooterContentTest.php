<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Подвал сайта — единственная CMS-секция без route_name (глобальная, см.
 * миграцию make_site_sections_route_name_nullable и
 * Domain\Content\Actions\Content\LoadSiteFooter): должна быть видна на
 * любой публичной странице сразу, не только на той, что её создала.
 */
class FooterContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_content_is_visible_on_different_routes(): void
    {
        $section = SiteSection::factory()->create(['key' => 'footer', 'route_name' => null]);

        ContentBlock::factory()->create([
            'site_section_id' => $section->id,
            'key' => 'footer',
            'type' => 'footer_info',
            'content' => [
                'rights' => 'Все права защищены тестом',
                'age_notice' => '18+ тестовое предупреждение',
            ],
        ]);

        $this->get(route('home'))->assertOk()
            ->assertSee('Все права защищены тестом')
            ->assertSee('18+ тестовое предупреждение');

        $this->get(route('about'))->assertOk()
            ->assertSee('Все права защищены тестом')
            ->assertSee('18+ тестовое предупреждение');
    }

    public function test_page_renders_without_error_when_footer_block_is_missing(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_inactive_footer_section_is_not_rendered(): void
    {
        $section = SiteSection::factory()->create(['key' => 'footer', 'route_name' => null, 'is_active' => false]);

        ContentBlock::factory()->create([
            'site_section_id' => $section->id,
            'type' => 'footer_info',
            'content' => ['rights' => 'Скрытый текст подвала'],
        ]);

        $this->get(route('home'))->assertOk()->assertDontSee('Скрытый текст подвала');
    }
}
