<?php

namespace Tests\Feature\Content;

use ContentCms\Models\SiteMenu;
use ContentCms\Models\SiteMenuItem;
use Database\Seeders\MoonshinePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_seeder_creates_permissions_for_package_models(): void
    {
        $this->seed(MoonshinePermissionSeeder::class);
        foreach (['SiteSection', 'SiteMenu', 'SiteMenuItem', 'ContentBlock', 'ContentBlockItem'] as $model) {
            $this->assertDatabaseHas('moonshine_permissions', ['model' => 'ContentCms\\Models\\'.$model]);
            $this->assertDatabaseMissing('moonshine_permissions', ['model' => 'Domain\\Content\\Models\\'.$model]);
        }
        $before = DB::table('moonshine_permissions')->orderBy('id')->get()->toJson();
        $this->seed(MoonshinePermissionSeeder::class);
        $this->assertSame($before, DB::table('moonshine_permissions')->orderBy('id')->get()->toJson());
    }

    public function test_initial_menu_schema_requires_key_and_unique_key_within_menu(): void
    {
        $key = collect(Schema::getColumns('site_menu_items'))->firstWhere('name', 'key');
        $this->assertFalse($key['nullable']);
        $this->assertSame('varchar(100)', $key['type']);
        $index = collect(Schema::getIndexes('site_menu_items'))->firstWhere('name', 'site_menu_items_menu_key_unique');
        $this->assertNotNull($index);
        $this->assertTrue($index['unique']);
        $this->assertSame(['site_menu_id', 'key'], $index['columns']);
    }

    public function test_database_rejects_null_menu_key(): void
    {
        $item = SiteMenuItem::factory()->external()->create();
        $this->expectException(QueryException::class);
        DB::table('site_menu_items')->where('id', $item->id)->update(['key' => null]);
    }

    public function test_database_rejects_duplicate_keys_in_same_menu(): void
    {
        $menu = SiteMenu::factory()->create();
        SiteMenuItem::factory()->external()->create(['site_menu_id' => $menu->id, 'key' => 'first']);
        $second = SiteMenuItem::factory()->external()->create(['site_menu_id' => $menu->id, 'key' => 'second']);
        $this->expectException(QueryException::class);
        DB::table('site_menu_items')->where('id', $second->id)->update(['key' => 'first']);
    }

    public function test_same_key_is_allowed_in_different_menus(): void
    {
        $menus = SiteMenu::factory()->count(2)->create();
        foreach ($menus as $menu) {
            SiteMenuItem::factory()->external()->create(['site_menu_id' => $menu->id, 'key' => 'shared']);
            $this->assertFalse(SiteMenuItem::scoped(['site_menu_id' => $menu->id])->isBroken());
        }
        $this->assertSame(2, SiteMenuItem::where('key', 'shared')->count());
    }
}
