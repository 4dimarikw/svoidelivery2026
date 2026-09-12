<?php

namespace Tests\Feature;

use Database\Seeders\CurrentSiteDataSeeder;
use Domain\Content\Models\SiteMenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Infrastructure\Settings\SiteSettings;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurrentSiteDataSeederTest extends TestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'seed_snapshot_test',
            'database.connections.seed_snapshot_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'settings.cache.store' => 'array',
        ]);
        foreach ([
            '2025_12_23_095157_create_settings_table.php',
            '2026_08_06_120000_create_site_content_tables.php',
            '2026_08_09_083517_create_telegraph_bots_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->fixture = require database_path('seeders/data/current-site.php');
        Http::preventStrayRequests();
    }

    private function seeder(): CurrentSiteDataSeeder
    {
        $tokens = array_fill_keys(array_column($this->fixture['telegraph_bots'], 'id'), '123456:test-only-token');

        return new CurrentSiteDataSeeder($this->fixture, $tokens);
    }

    public function test_restores_snapshot_and_preserves_existing_edits_on_second_run(): void
    {
        $this->seeder()->run();
        foreach ($this->fixture as $table => $rows) {
            $this->assertSame(count($rows), DB::table($table)->count(), $table);
            if ($table !== 'telegraph_bots' && $table !== 'settings') {
                $this->assertEquals($rows, DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
            }
        }
        foreach ($this->fixture['settings'] as $row) {
            $this->assertDatabaseHas('settings', [
                'group' => $row['group'], 'name' => $row['name'], 'payload' => $row['payload'],
            ]);
        }
        foreach ($this->fixture['site_menus'] as $menu) {
            $this->assertFalse(SiteMenuItem::scoped(['site_menu_id' => $menu['id']])->isBroken());
        }
        DB::table('settings')->where('group', 'site')->where('name', 'site_name')->update(['payload' => '"Edited"']);
        DB::table('site_sections')->update(['title' => 'Edited']);
        DB::table('telegraph_bots')->update(['name' => 'Edited bot']);
        $before = $this->snapshot();
        $this->seeder()->run();
        $this->assertEquals($before, $this->snapshot());
    }

    public function test_partial_cms_is_not_mixed_with_snapshot_ids(): void
    {
        DB::table('site_sections')->insert([
            'id' => 999, 'key' => 'custom', 'title' => 'Custom',
        ]);
        $this->seeder()->run();
        $this->assertSame(1, DB::table('site_sections')->count());
        $this->assertSame(0, DB::table('site_menus')->count());
        $this->assertSame(0, DB::table('content_blocks')->count());
    }

    public function test_missing_bot_token_rolls_back_import(): void
    {
        try {
            (new CurrentSiteDataSeeder($this->fixture, []))->run();
            $this->fail('Missing credentials must be reported.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('current-site.local.php', $exception->getMessage());
        }
        foreach (array_keys($this->fixture) as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    public static function cacheModes(): array
    {
        return ['enabled' => [true], 'disabled' => [false]];
    }

    #[DataProvider('cacheModes')]
    public function test_clears_default_and_repository_settings_caches(bool $enabled): void
    {
        config([
            'settings.cache.enabled' => $enabled,
            'settings.cache.prefix' => 'seed-default',
            'settings.repositories.database.cache' => [
                'enabled' => $enabled, 'store' => 'array', 'prefix' => 'seed-repository',
            ],
        ]);
        $keys = array_map(fn ($prefix) => $prefix.'.settings.'.SiteSettings::cacheKey(), [
            'seed-default', 'seed-repository',
        ]);
        foreach ($keys as $key) {
            Cache::store('array')->put($key, 'stale-settings');
        }
        Cache::store('array')->put('unrelated', 'keep');

        $this->seeder()->run();

        foreach ($keys as $key) {
            $this->assertFalse(Cache::store('array')->has($key));
        }
        $this->assertSame('keep', Cache::store('array')->get('unrelated'));
    }

    private function snapshot(): array
    {
        return array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toArray(), array_keys($this->fixture));
    }
}
