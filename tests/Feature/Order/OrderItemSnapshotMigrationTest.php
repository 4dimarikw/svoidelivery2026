<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use Database\Seeders\CategorySeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Проверяет создание и откат схемы исключительно в выделенной тестовой базе. */
class OrderItemSnapshotMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('svoidelivery2026_test', config('database.connections.mysql.database'));
        $this->assertEmpty(config('database.connections.mysql.url'));
        $this->assertSame('svoidelivery2026_test', DB::selectOne('select database() as db')->db);
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('ftp');
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]), Artisan::output());
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_fresh_schema_contains_final_columns_and_constraints(): void
    {
        $columns = collect(Schema::getColumns('order_items'))->keyBy('name');
        foreach (['product_name' => 512, 'product_external_code' => 64, 'product_brand' => 255] as $name => $length) {
            $this->assertSame("varchar($length)", $columns[$name]['type']);
            $this->assertSame($name === 'product_brand', $columns[$name]['nullable']);
        }
        $foreign = collect(Schema::getForeignKeys('order_items'))->firstWhere('name', 'order_items_product_restrict_foreign');
        $this->assertNotNull($foreign);
        $this->assertSame(['product_id'], $foreign['columns']);
        $this->assertSame('products', $foreign['foreign_table']);
        $this->assertSame('restrict', strtolower($foreign['on_delete']));
        $this->assertSame('cascade', strtolower($foreign['on_update']));

        foreach ([
            'user_profiles' => ['social_links', 'is_bot_active', 'last_bot_error', 'bot_checked_at'],
            'telegraph_chats' => ['moonshine_user_id'],
            'site_sections' => ['route_name'],
            'order_customers' => ['messenger_url'],
            'carts' => ['checkout_token'],
            'orders' => ['checkout_key'],
        ] as $table => $names) {
            $columns = collect(Schema::getColumns($table))->keyBy('name');
            foreach ($names as $name) {
                $this->assertArrayHasKey($name, $columns->all());
                $this->assertTrue($columns[$name]['nullable'], "$table.$name");
            }
        }
        $this->assertFalse(Schema::hasColumn('user_profiles', 'telegram_url'));
        $this->assertFalse(Schema::hasColumn('user_profiles', 'vk_url'));
        $this->assertSame('json', collect(Schema::getColumns('user_profiles'))->firstWhere('name', 'social_links')['type_name']);
        $this->assertTrue(Schema::hasIndex('orders', ['checkout_key'], 'unique'));
        $this->assertTrue(Schema::hasIndex('telegraph_chats', ['moonshine_user_id', 'telegraph_bot_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('order_items', ['order_id', 'product_id']));
        $this->assertTrue(Schema::hasIndex('order_items', ['created_at', 'order_id']));
        $chatForeign = collect(Schema::getForeignKeys('telegraph_chats'))->firstWhere('columns', ['moonshine_user_id']);
        $this->assertSame('moonshine_users', $chatForeign['foreign_table']);
        $this->assertSame('set null', strtolower($chatForeign['on_delete']));
    }

    public function test_initial_registry_is_available_without_seeders_and_preserves_admin_edits(): void
    {
        $this->assertSame(13, DB::table('categories')->count());
        $this->assertFalse(Schema::hasTable('category_match_rules'));
        $this->assertCount(23, config('category_matching.rules'));
        $this->assertSame(range(10, 230, 10), array_column(config('category_matching.rules'), 'priority'));
        $this->assertSame('pet', DB::table('categories')->where('slug', 'pet-tare-packages')->value('container_code'));
        $this->assertEquals(1, DB::table('categories')->where('slug', 'beer')->value('expects_volume'));
        $this->assertEquals(0, DB::table('categories')->where('slug', 'not-defined')->value('expects_volume'));
        $this->assertSame(0, Artisan::call('catalog:validate-registry'), Artisan::output());

        DB::table('categories')->where('slug', 'beer')->update(['name' => 'Название из админки', 'expects_volume' => false]);
        $categories = DB::table('categories')->orderBy('id')->get()->toJson();
        $rules = config('category_matching.rules');
        $this->seed(CategorySeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($categories, DB::table('categories')->orderBy('id')->get()->toJson());
        $this->assertSame($rules, config('category_matching.rules'));
    }

    public function test_all_migrations_roll_back_and_apply_again_with_settings(): void
    {
        $tables = Schema::getTableListing('svoidelivery2026_test', schemaQualified: false);
        $migrations = DB::table('migrations')->count();
        $settings = DB::table('settings')->orderBy('group')->orderBy('name')->get(['group', 'name', 'payload'])->toJson();
        $this->assertGreaterThan(0, $migrations);
        $this->assertSame(0, Artisan::call('migrate:reset', ['--force' => true]), Artisan::output());
        $this->assertFalse(Schema::hasTable('order_items'));
        $this->assertFalse(Schema::hasTable('order_deliveries'));
        $this->assertFalse(Schema::hasTable('settings'));
        $this->assertSame(['migrations'], Schema::getTableListing('svoidelivery2026_test', schemaQualified: false));
        $this->assertSame(0, DB::table('migrations')->count());
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());
        $this->assertSame($migrations, DB::table('migrations')->count());
        $this->assertSame($tables, Schema::getTableListing('svoidelivery2026_test', schemaQualified: false));
        $this->assertSame($settings, DB::table('settings')->orderBy('group')->orderBy('name')->get(['group', 'name', 'payload'])->toJson());
        $this->assertFalse(Schema::hasTable('category_match_rules'));
        $this->assertCount(23, config('category_matching.rules'));
    }

    public function test_fresh_with_seed_creates_current_content_and_permissions(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]), Artisan::output());
        $this->assertFalse(Schema::hasTable('category_match_rules'));
        $this->assertSame(0, Artisan::call('catalog:validate-registry'), Artisan::output());

        $blocks = DB::table('content_blocks')->where('type', 'about_info')->get();
        $this->assertNotEmpty($blocks);
        foreach ($blocks as $block) {
            $content = json_decode($block->content, true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(5, $content['ordering_rules']);
            $this->assertCount(3, $content['promotions']);
        }
        foreach (['SiteSection', 'SiteMenu', 'SiteMenuItem', 'ContentBlock', 'ContentBlockItem'] as $model) {
            $this->assertDatabaseHas('moonshine_permissions', ['model' => 'ContentCms\\Models\\'.$model]);
            $this->assertDatabaseMissing('moonshine_permissions', ['model' => 'Domain\\Content\\Models\\'.$model]);
        }

        $this->assertSame(0, Artisan::call('migrate:reset', ['--force' => true]), Artisan::output());
        $this->assertSame(['migrations'], Schema::getTableListing('svoidelivery2026_test', schemaQualified: false));
        $this->assertSame(0, Artisan::call('migrate', ['--seed' => true, '--force' => true]), Artisan::output());
        $this->assertSame($blocks->toJson(), DB::table('content_blocks')->where('type', 'about_info')->get()->toJson());
        $this->assertFalse(Schema::hasTable('category_match_rules'));
    }
}
