<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use Domain\Catalog\Models\Product;
use Domain\Order\Actions\BackfillOrderItemSnapshots;
use Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/** DDL tests deliberately use no outer transaction; only the guarded test database. */
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
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')),
            fn ($path) => ! str_starts_with(basename($path), '2026_09_09_100')));
        $paths = array_merge($paths, app('migrator')->paths());
        Artisan::call('migrate:fresh', ['--force' => true, '--path' => $paths, '--realpath' => true]);
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('ftp');
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function migration(string $name): object
    {
        return require database_path('migrations/2026_09_09_'.$name.'.php');
    }

    private function legacyItems(int $count = 1): Product
    {
        $product = Product::factory()->create(['brand' => null, 'name' => 'Пиво &amp; сыр']);
        $order = Order::factory()->create(['amount' => 999]);
        DB::table('order_items')->insert(array_fill(0, $count, [
            'order_id' => $order->id, 'product_id' => $product->id,
            'price' => '12.34', 'quantity' => 3,
            'created_at' => '2026-08-01 10:00:00', 'updated_at' => '2026-08-02 10:00:00',
        ]));

        return $product;
    }

    public function test_backfill_preserves_all_business_data_across_chunks_and_is_repeatable(): void
    {
        $product = $this->legacyItems(501);
        $beforeItems = DB::table('order_items')->orderBy('id')->get()->toJson();
        $beforeOrders = DB::table('orders')->orderBy('id')->get()->toJson();
        $this->migration('100000_protect_order_item_products_and_add_snapshots')->up();
        Event::fake();
        $this->migration('100100_backfill_order_item_snapshots')->up();
        $columns = ['id', 'order_id', 'product_id', 'price', 'quantity', 'created_at', 'updated_at'];
        $this->assertSame($beforeItems, DB::table('order_items')->select($columns)->orderBy('id')->get()->toJson());
        $this->assertSame($beforeOrders, DB::table('orders')->orderBy('id')->get()->toJson());
        $this->assertSame(501, DB::table('order_items')->where('product_external_code', $product->external_code)->count());
        $this->assertSame('Пиво & сыр', DB::table('order_items')->value('product_name'));
        $snapshots = DB::table('order_items')->orderBy('id')->get()->toJson();
        DB::table('products')->where('id', $product->id)->update(['name' => 'Изменено', 'brand' => 'Новый бренд']);
        app(BackfillOrderItemSnapshots::class)->execute();
        $this->assertSame($snapshots, DB::table('order_items')->orderBy('id')->get()->toJson());
        $this->migration('100200_require_order_item_snapshots')->up();
        $nullable = DB::select("SELECT IS_NULLABLE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
            AND COLUMN_NAME IN ('product_name', 'product_external_code')");
        $this->assertSame(['NO', 'NO'], array_column($nullable, 'IS_NULLABLE'));
        Event::assertNothingDispatched();
    }

    public function test_final_migration_refuses_unfilled_snapshots(): void
    {
        $this->legacyItems();
        $this->migration('100000_protect_order_item_products_and_add_snapshots')->up();
        $id = DB::table('order_items')->value('id');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage((string) $id);
        $this->migration('100200_require_order_item_snapshots')->up();
    }

    public function test_invalid_source_stops_backfill_before_any_snapshot_is_written(): void
    {
        $product = $this->legacyItems(2);
        $this->migration('100000_protect_order_item_products_and_add_snapshots')->up();
        DB::table('products')->where('id', $product->id)->update(['external_code' => '']);
        try {
            app(BackfillOrderItemSnapshots::class)->execute();
            $this->fail('Expected invalid source failure');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Нет достоверного источника', $e->getMessage());
        }
        $this->assertSame(2, DB::table('order_items')->whereNull('product_name')->count());
    }

    public function test_partial_snapshot_preserves_existing_values_and_down_is_non_destructive(): void
    {
        $product = $this->legacyItems();
        $this->migration('100000_protect_order_item_products_and_add_snapshots')->up();
        DB::table('order_items')->update(['product_name' => 'Уже сохранено', 'product_brand' => 'Старый бренд']);
        app(BackfillOrderItemSnapshots::class)->execute();
        $item = DB::table('order_items')->first();
        $this->assertSame('Уже сохранено', $item->product_name);
        $this->assertSame('Старый бренд', $item->product_brand);
        $this->assertSame($product->external_code, $item->product_external_code);
        $this->migration('100200_require_order_item_snapshots')->up();
        foreach (['100000_protect_order_item_products_and_add_snapshots', '100100_backfill_order_item_snapshots', '100200_require_order_item_snapshots'] as $name) {
            try {
                $this->migration($name)->down();
                $this->fail('Expected safe rollback refusal');
            } catch (RuntimeException) {
                $this->assertEquals($item, DB::table('order_items')->first());
            }
        }
    }
}
