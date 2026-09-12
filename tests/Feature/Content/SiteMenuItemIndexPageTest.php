<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

/**
 * Проверяет пустую колонку «Родитель» у корневого пункта меню. Обработчик formatted должен учитывать parent_id,
 * поскольку nestedset-связь может возвращать текущую модель для корневой записи.
 */
class SiteMenuItemIndexPageTest extends TestCase
{
    use RefreshDatabase;

    private function superuser(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'email' => 'site-menu-item-smoke@test.local',
            'password' => bcrypt('password'),
            'name' => 'Smoke Test',
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
    }

    public function test_root_item_does_not_show_its_own_label_as_parent(): void
    {
        $menu = SiteMenu::factory()->create();

        SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'parent_id' => null,
            'label' => 'Корневой пункт',
        ]);

        $response = $this->actingAs($this->superuser(), 'moonshine')
            ->get('/admin/resource/site-menu-item-resource/site-menu-item-index-page');

        $response->assertOk();

        // Подпись корневого пункта отображается только в колонке «Подпись».
        self::assertSame(1, substr_count($response->getContent(), 'Корневой пункт'));
    }

    public function test_child_item_still_shows_its_parent_label(): void
    {
        $menu = SiteMenu::factory()->create();

        $parent = SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'parent_id' => null,
            'label' => 'Родительский пункт',
        ]);

        SiteMenuItem::factory()->create([
            'site_menu_id' => $menu->id,
            'parent_id' => $parent->id,
            'label' => 'Дочерний пункт',
        ]);

        $response = $this->actingAs($this->superuser(), 'moonshine')
            ->get('/admin/resource/site-menu-item-resource/site-menu-item-index-page');

        $response->assertOk();

        // Родительский label должен появиться: один раз в его собственной
        // строке ("Подпись") и один раз в колонке "Родитель" дочерней строки.
        self::assertSame(2, substr_count($response->getContent(), 'Родительский пункт'));
    }
}
