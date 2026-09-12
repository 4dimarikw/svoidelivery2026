<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use ContentCms\Models\ContentBlock;
use ContentCms\Models\SiteMenu;
use ContentCms\Models\SiteMenuItem;
use ContentCms\Models\SiteSection;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * database/seeders/data/site-content.php ничем не покрыт — ни один тест не
 * гонял сам SiteContentSeeder (он и не зарегистрирован в DatabaseSeeder,
 * запускается вручную). Закрепляет, что fixture валиден и накатывается на
 * пустую БД без исключений, с ожидаемыми section/menu/block значениями.
 */
class SiteContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_fixture_into_empty_database(): void
    {
        (new SiteContentSeeder)->run();

        $about = SiteSection::where('key', 'about')->firstOrFail();
        $this->assertSame('about', $about->route_name);
        $this->assertSame(0, $about->sort_order);

        $footer = SiteSection::where('key', 'footer')->firstOrFail();
        $this->assertNull($footer->route_name);
        $this->assertSame(30, $footer->sort_order);

        $menu = SiteMenu::where('key', 'main')->firstOrFail();
        $this->assertSame('Главное', $menu->title);

        $this->assertTrue(SiteMenuItem::where('key', 'about')->where('site_menu_id', $menu->id)->exists());

        $aboutBlock = ContentBlock::where('site_section_id', $about->id)->where('key', 'about')->firstOrFail();
        $this->assertSame('about_info', $aboutBlock->type);
        $this->assertCount(0, $aboutBlock->items);
        $this->assertCount(5, $aboutBlock->content['ordering_rules']);
        $this->assertCount(3, $aboutBlock->content['promotions']);
        $fixture = require database_path('seeders/data/site-content.php');
        $this->assertEquals($fixture['blocks']['about']['content'], $aboutBlock->content);

        $footerBlock = ContentBlock::where('site_section_id', $footer->id)->where('key', 'footer')->firstOrFail();
        $this->assertSame('footer_info', $footerBlock->type);
    }

    public function test_second_run_is_a_no_op_when_cms_already_has_data(): void
    {
        (new SiteContentSeeder)->run();
        $sectionsBefore = SiteSection::count();

        (new SiteContentSeeder)->run();

        $this->assertSame($sectionsBefore, SiteSection::count());
    }
}
