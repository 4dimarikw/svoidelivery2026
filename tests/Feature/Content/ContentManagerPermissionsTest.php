<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use Database\Seeders\MoonshinePermissionSeeder;
use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\ContentBlockItem;
use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Domain\Content\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

/**
 * Роль Manager получила доступ к CMS-ресурсам (SiteSection/SiteMenu/
 * SiteMenuItem/ContentBlock/ContentBlockItem) только на редактирование
 * содержимого: без создания/удаления и без служебных полей (ключи,
 * маршруты, структура меню) — см. app/MoonShine/Traits/MoonshinePermissionPolicy,
 * ChecksSuperUser и MoonshinePermissionSeeder.
 */
class ContentManagerPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MoonshinePermissionSeeder::class);

        // Тесты переключают actingAs() между Manager и суперюзером внутри
        // одного метода; AuthenticateSession сверяет хэш пароля в сессии с
        // текущим пользователем и разлогинивает при "чужой" сессии.
        $this->withoutMiddleware(AuthenticateSession::class);
    }

    private function manager(): MoonshineUser
    {
        $roleId = MoonshineUserRole::query()->where('name', 'Manager')->value('id');

        return MoonshineUser::query()->create([
            'email' => 'manager@test.local',
            'password' => bcrypt('password'),
            'name' => 'Manager',
            'moonshine_user_role_id' => $roleId,
        ]);
    }

    private function superuser(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'email' => 'superuser@test.local',
            'password' => bcrypt('password'),
            'name' => 'Superuser',
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
    }

    public function test_manager_can_view_index_and_form_pages(): void
    {
        $section = SiteSection::factory()->create();
        $menu = SiteMenu::factory()->create();
        $menuItem = SiteMenuItem::factory()->create(['site_menu_id' => $menu->id]);
        $block = ContentBlock::factory()->create(['site_section_id' => $section->id, 'type' => 'about_info']);
        $item = ContentBlockItem::factory()->create(['content_block_id' => $block->id, 'group_key' => 'ordering_rules', 'content' => ['text' => 'Пункт правил']]);

        $manager = $this->manager();

        $pages = [
            'site-section-resource/site-section-index-page',
            "site-section-resource/site-section-form-page/{$section->id}",
            'site-menu-resource/site-menu-index-page',
            "site-menu-resource/site-menu-form-page/{$menu->id}",
            'site-menu-item-resource/site-menu-item-index-page',
            "site-menu-item-resource/site-menu-item-form-page/{$menuItem->id}",
            'content-block-resource/content-block-index-page',
            "content-block-resource/content-block-form-page/{$block->id}",
            'content-block-item-resource/content-block-item-index-page',
            "content-block-item-resource/content-block-item-form-page/{$item->id}",
        ];

        foreach ($pages as $page) {
            $this->actingAs($manager, 'moonshine')
                ->get("/admin/resource/{$page}")
                ->assertOk();
        }
    }

    public function test_manager_cannot_create_content_records(): void
    {
        $section = SiteSection::factory()->create();
        $menu = SiteMenu::factory()->create();
        $block = ContentBlock::factory()->create(['site_section_id' => $section->id, 'type' => 'about_info']);

        $manager = $this->manager();

        $resources = [
            'site-section-resource',
            'site-menu-resource',
            'site-menu-item-resource',
            'content-block-resource',
            'content-block-item-resource',
        ];

        foreach ($resources as $resource) {
            $this->actingAs($manager, 'moonshine')
                ->post("/admin/resource/{$resource}/crud")
                ->assertForbidden();
        }

        self::assertSame(1, SiteSection::query()->count());
        self::assertSame(1, SiteMenu::query()->count());
        self::assertSame(1, ContentBlock::query()->count());
    }

    public function test_manager_cannot_delete_content_records(): void
    {
        $section = SiteSection::factory()->create();
        $menu = SiteMenu::factory()->create();
        $menuItem = SiteMenuItem::factory()->create(['site_menu_id' => $menu->id]);
        $block = ContentBlock::factory()->create(['site_section_id' => $section->id, 'type' => 'about_info']);
        $item = ContentBlockItem::factory()->create(['content_block_id' => $block->id, 'group_key' => 'ordering_rules', 'content' => ['text' => 'Пункт правил']]);

        $manager = $this->manager();

        $targets = [
            "site-section-resource/crud/{$section->id}",
            "site-menu-resource/crud/{$menu->id}",
            "site-menu-item-resource/crud/{$menuItem->id}",
            "content-block-resource/crud/{$block->id}",
            "content-block-item-resource/crud/{$item->id}",
        ];

        foreach ($targets as $target) {
            $this->actingAs($manager, 'moonshine')
                ->delete("/admin/resource/{$target}")
                ->assertForbidden();
        }

        self::assertTrue(SiteSection::query()->whereKey($section->id)->exists());
        self::assertTrue(SiteMenu::query()->whereKey($menu->id)->exists());
        self::assertTrue(SiteMenuItem::query()->whereKey($menuItem->id)->exists());
        self::assertTrue(ContentBlock::query()->whereKey($block->id)->exists());
        self::assertTrue(ContentBlockItem::query()->whereKey($item->id)->exists());
    }

    public function test_manager_can_update_content_without_touching_service_fields(): void
    {
        $section = SiteSection::factory()->create(['key' => 'promo', 'route_name' => 'home', 'fragment' => 'promo-anchor']);
        $menu = SiteMenu::factory()->create(['key' => 'main']);
        $menuItem = SiteMenuItem::factory()->create(['site_menu_id' => $menu->id, 'key' => 'item-fixed', 'label' => 'Старая подпись']);
        $block = ContentBlock::factory()->create(['site_section_id' => $section->id, 'type' => 'about_info', 'key' => 'block-fixed', 'title' => 'Старый блок']);
        $item = ContentBlockItem::factory()->create(['content_block_id' => $block->id, 'key' => 'item-fixed-2', 'title' => 'Старый элемент', 'group_key' => 'ordering_rules', 'content' => ['text' => 'Пункт правил']]);

        $manager = $this->manager();

        $this->actingAs($manager, 'moonshine')
            ->put("/admin/resource/site-section-resource/crud/{$section->id}", [
                'title' => 'Новый раздел',
                'sort_order' => $section->sort_order,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $section->refresh();
        self::assertSame('Новый раздел', $section->title);
        self::assertSame('promo', $section->key);
        self::assertSame('home', $section->route_name);
        self::assertSame('promo-anchor', $section->fragment);

        $this->actingAs($manager, 'moonshine')
            ->put("/admin/resource/site-menu-resource/crud/{$menu->id}", [
                'title' => 'Новое меню',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $menu->refresh();
        self::assertSame('Новое меню', $menu->title);
        self::assertSame('main', $menu->key);

        $this->actingAs($manager, 'moonshine')
            ->put("/admin/resource/site-menu-item-resource/crud/{$menuItem->id}", [
                'label' => 'Новая подпись',
                'open_in_new_tab' => 0,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $menuItem->refresh();
        self::assertSame('Новая подпись', $menuItem->label);
        self::assertSame('item-fixed', $menuItem->key);
        self::assertSame($menu->id, $menuItem->site_menu_id);

        $this->actingAs($manager, 'moonshine')
            ->put("/admin/resource/content-block-resource/crud/{$block->id}", [
                'title' => 'Новый блок',
                'sort_order' => $block->sort_order,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $block->refresh();
        self::assertSame('Новый блок', $block->title);
        self::assertSame('block-fixed', $block->key);
        self::assertSame($section->id, $block->site_section_id);

        $this->actingAs($manager, 'moonshine')
            ->put("/admin/resource/content-block-item-resource/crud/{$item->id}", [
                'title' => 'Новый элемент',
                'sort_order' => $item->sort_order,
                'is_active' => 1,
                'content' => ['text' => 'Новый пункт правил'],
            ])
            ->assertRedirect();

        $item->refresh();
        self::assertSame('Новый элемент', $item->title);
        self::assertSame('item-fixed-2', $item->key);
        self::assertSame($block->id, $item->content_block_id);
    }

    public function test_service_field_labels_hidden_from_manager_shown_to_superuser(): void
    {
        $section = SiteSection::factory()->create();
        $menu = SiteMenu::factory()->create();
        $menuItem = SiteMenuItem::factory()->create(['site_menu_id' => $menu->id]);
        $block = ContentBlock::factory()->create(['site_section_id' => $section->id, 'type' => 'about_info']);
        $item = ContentBlockItem::factory()->create(['content_block_id' => $block->id, 'group_key' => 'ordering_rules', 'content' => ['text' => 'Пункт правил']]);

        $manager = $this->manager();
        $superuser = $this->superuser();

        $forms = [
            "site-section-resource/site-section-form-page/{$section->id}",
            "site-menu-resource/site-menu-form-page/{$menu->id}",
            "content-block-resource/content-block-form-page/{$block->id}",
            "content-block-item-resource/content-block-item-form-page/{$item->id}",
        ];

        foreach ($forms as $form) {
            $managerHtml = $this->actingAs($manager, 'moonshine')
                ->get("/admin/resource/{$form}")
                ->assertOk()
                ->getContent();

            self::assertStringNotContainsString('Ключ', $managerHtml, "Manager form should not show service field on {$form}");
            self::assertStringContainsString('Название', $managerHtml, "Content field should stay visible on {$form}");

            $superuserHtml = $this->actingAs($superuser, 'moonshine')
                ->get("/admin/resource/{$form}")
                ->assertOk()
                ->getContent();

            self::assertStringContainsString('Ключ', $superuserHtml, "Superuser form should show service field on {$form}");
        }

        $managerMenuItemHtml = $this->actingAs($manager, 'moonshine')
            ->get("/admin/resource/site-menu-item-resource/site-menu-item-form-page/{$menuItem->id}")
            ->assertOk()
            ->getContent();

        self::assertStringNotContainsString('Ключ', $managerMenuItemHtml);
        self::assertStringNotContainsString('Внешний URL', $managerMenuItemHtml);
        self::assertStringContainsString('Подпись', $managerMenuItemHtml);

        $superuserMenuItemHtml = $this->actingAs($superuser, 'moonshine')
            ->get("/admin/resource/site-menu-item-resource/site-menu-item-form-page/{$menuItem->id}")
            ->assertOk()
            ->getContent();

        self::assertStringContainsString('Ключ', $superuserMenuItemHtml);
        self::assertStringContainsString('Внешний URL', $superuserMenuItemHtml);
    }

    public function test_manager_does_not_see_branch_transfer_button(): void
    {
        $menu = SiteMenu::factory()->create();
        SiteMenuItem::factory()->create(['site_menu_id' => $menu->id]);

        $manager = $this->manager();
        $superuser = $this->superuser();

        $managerHtml = $this->actingAs($manager, 'moonshine')
            ->get('/admin/resource/site-menu-item-resource/site-menu-item-index-page')
            ->assertOk()
            ->getContent();

        self::assertStringNotContainsString('transferItem', $managerHtml);

        $superuserHtml = $this->actingAs($superuser, 'moonshine')
            ->get('/admin/resource/site-menu-item-resource/site-menu-item-index-page')
            ->assertOk()
            ->getContent();

        self::assertStringContainsString('transferItem', $superuserHtml);
    }
}
