<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use Domain\Auth\Models\User;
use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Domain\Content\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Главное меню шапки/бургера мобильной панели ($menu) — сидируется
 * View Composer'ом (AppServiceProvider::boot()) через
 * Domain\Content\Actions\Content\LoadSiteMenu, а не пропом страницы, так
 * что должно быть видно на ЛЮБОЙ публичной странице, не только home/about.
 */
class HeaderMenuTest extends TestCase
{
    use RefreshDatabase;

    private function countTableQueries(array $log, string $table): int
    {
        return count(array_filter(
            $log,
            fn (array $q): bool => (bool) preg_match('/from `'.$table.'`/', $q['query'])
        ));
    }

    public function test_menu_renders_root_items_with_nested_child_in_order(): void
    {
        $menu = SiteMenu::factory()->create(['key' => 'main']);
        $section = SiteSection::factory()->create(['route_name' => 'about']);

        $first = SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'label' => 'Первый',
            'site_section_id' => $section->id,
        ]);
        $second = SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'label' => 'Второй',
            'site_section_id' => $section->id,
        ]);
        SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'parent_id' => $first->id,
            'label' => 'Дочерний',
            'site_section_id' => $section->id,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSeeInOrder(['Первый', 'Второй'], false);
        $response->assertSee('Дочерний', false);
    }

    public function test_inactive_menu_item_is_not_rendered(): void
    {
        $menu = SiteMenu::factory()->create(['key' => 'main']);
        $section = SiteSection::factory()->create(['route_name' => 'about']);
        SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'label' => 'Скрытый пункт',
            'site_section_id' => $section->id,
            'is_active' => false,
        ]);

        $this->get(route('home'))->assertOk()->assertDontSee('Скрытый пункт');
    }

    public function test_menu_item_linked_to_inactive_section_is_not_rendered(): void
    {
        $menu = SiteMenu::factory()->create(['key' => 'main']);
        $section = SiteSection::factory()->create(['route_name' => 'about', 'is_active' => false]);
        SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'label' => 'Пункт со скрытой секцией',
            'site_section_id' => $section->id,
        ]);

        $this->get(route('home'))->assertOk()->assertDontSee('Пункт со скрытой секцией');
    }

    public function test_external_link_item_opens_in_new_tab_with_safe_rel(): void
    {
        $menu = SiteMenu::factory()->create(['key' => 'main']);
        SiteMenuItem::factory()->external()->create([
            'site_menu_id' => $menu->id,
            'label' => 'Внешняя ссылка',
            'external_url' => 'https://example.com',
            'open_in_new_tab' => true,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_menu_label_is_escaped(): void
    {
        $menu = SiteMenu::factory()->create(['key' => 'main']);
        $section = SiteSection::factory()->create(['route_name' => 'about']);
        SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'label' => '<script>alert(1)</script>',
            'site_section_id' => $section->id,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_menu_is_visible_on_a_page_whose_controller_does_not_call_load_public_page(): void
    {
        // account.profile.edit не проходит через LoadPublicPage — меню
        // должно попасть туда только через View Composer.
        $menu = SiteMenu::factory()->create(['key' => 'main']);
        $section = SiteSection::factory()->create(['route_name' => 'about']);
        SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'label' => 'Виден везде',
            'site_section_id' => $section->id,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.profile.edit'))
            ->assertOk()
            ->assertSee('Виден везде', false);
    }

    public function test_missing_active_main_menu_renders_page_without_errors(): void
    {
        // <x-ui.nav-menu> оборачивает себя в @if (! empty($items)) — без
        // активного меню main <nav id="main-nav"> не попадает в DOM вовсе.
        $this->get(route('home'))->assertOk()->assertDontSee('id="main-nav"', false);
    }

    public function test_menu_queries_are_memoized_per_request(): void
    {
        $menu = SiteMenu::factory()->create(['key' => 'main']);
        $section = SiteSection::factory()->create(['route_name' => 'home']);
        SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'label' => 'Пункт',
            'site_section_id' => $section->id,
        ]);

        DB::enableQueryLog();
        // home вызывает и LoadPublicPage (secion+menu), и composer шапки/
        // мобильной панели — без мемоизации singleton'а дерево строилось бы
        // трижды (LoadPublicPage + header + mobile-nav).
        $this->get(route('home'))->assertOk();
        $log = DB::getQueryLog();

        $this->assertSame(1, $this->countTableQueries($log, 'site_menus'));
        $this->assertSame(1, $this->countTableQueries($log, 'site_menu_items'));
    }
}
