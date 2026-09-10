<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Events\Order\OrderCreated;
use App\Events\Order\OrderStatusChanged;
use Domain\Auth\Models\User;
use Domain\Cart\Models\Cart;
use Domain\Catalog\Models\Product;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderItem;
use Domain\Profile\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Leeto\Seo\Models\Seo;
use PDOException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Реальные commit и разные процессы: RefreshDatabase здесь скрывает данные от worker. */
class CheckoutConcurrencyTest extends TestCase
{
    protected function tearDown(): void
    {
        // Следующий тест с RefreshDatabase должен заново подготовить чистую схему.
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('svoidelivery2026_test', config('database.connections.mysql.database'));
        $this->assertEmpty(config('database.connections.mysql.url'));
        $this->assertSame('svoidelivery2026_test', DB::selectOne('select database() as db')->db);
        Artisan::call('migrate:fresh', ['--force' => true]);
        Http::preventStrayRequests();
        Event::fake([OrderCreated::class, OrderStatusChanged::class]);
    }

    private function buyer(Product $product): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $address = Address::factory()->for($user)->create();
        cart()->increment($product);

        return [
            'operation' => 'checkout', 'user_id' => $user->id,
            'payload' => [
                'checkout_key' => cart()->checkoutToken(),
                'address_id' => $address->id,
                'first_name' => 'Иван', 'last_name' => 'Иванов',
                'phone' => '+79991234567', 'messenger_url' => 'https://t.me/ivan',
            ],
        ];
    }

    /** Блокируем товар до старта обоих процессов, чтобы запросы реально перекрывались. */
    private function race(Product $product, array $operations, array $orderIds = []): array
    {
        $connection = config('database.connections.mysql');
        $processes = [];
        DB::beginTransaction();
        try {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            Order::whereKey($orderIds)->orderBy('id')->lockForUpdate()->get();
            foreach ($operations as $operation) {
                $process = new Process([PHP_BINARY, '-d', 'sys_temp_dir='.sys_get_temp_dir(), base_path('tests/Support/checkout-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('storage/framework/testing/no-checkout-config.php'),
                    'DB_CONNECTION' => 'mysql', 'DB_HOST' => $connection['host'],
                    'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'],
                    'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'], 'DB_URL' => '',
                    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
                    'QUEUE_CONNECTION' => 'null',
                ], json_encode($operation, JSON_THROW_ON_ERROR), 20);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                // На Windows READY мог быть прочитан при start/isRunning; проверяем весь буфер.
                $deadline = microtime(true) + 10;
                while (! str_contains($process->getOutput(), 'READY') && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
                $this->assertTrue($process->isRunning());
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    public function test_two_buyers_cannot_sell_the_last_item_twice(): void
    {
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 1, 'in_stock' => true]);
        $results = $this->race($product, [$this->buyer($product), $this->buyer($product)]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 422], $statuses);
        $this->assertSame(1, Order::count());
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertFalse($product->fresh()->in_stock);
        $seo = Seo::where('url', route('product.show', $product, absolute: false))->firstOrFail();
        $this->assertStringContainsString('https://schema.org/OutOfStock', $seo->text);
        $this->assertSame(1, array_sum(array_column($results, 'events')));
        $this->assertSame([0], array_merge(...array_column($results, 'event_levels')));
    }

    public function test_parallel_item_price_edits_preserve_the_combined_total(): void
    {
        $product = Product::factory()->create();
        $first = OrderItem::factory()->for($product)->create(['price' => '10.00', 'quantity' => 2]);
        $second = OrderItem::factory()->for($product)->create(['order_id' => $first->order_id, 'price' => '5.00', 'quantity' => 3]);
        $userId = $first->order->user_id;
        $results = $this->race($product, [
            ['operation' => 'item_update', 'user_id' => $userId, 'item_id' => $first->id, 'changes' => ['price' => '20.00']],
            ['operation' => 'item_update', 'user_id' => $userId, 'item_id' => $second->id, 'changes' => ['price' => '7.00']],
        ], [$first->order_id]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame('61.00', $first->order->fresh()->getRawOriginal('amount'));
    }

    public function test_opposite_item_transfers_use_consistent_order_locks(): void
    {
        $product = Product::factory()->create();
        $first = OrderItem::factory()->for($product)->create(['price' => '10.00', 'quantity' => 2]);
        $second = OrderItem::factory()->for($product)->create(['price' => '5.00', 'quantity' => 3]);
        $results = $this->race($product, [
            ['operation' => 'item_update', 'user_id' => $first->order->user_id, 'item_id' => $first->id, 'changes' => ['order_id' => $second->order_id]],
            ['operation' => 'item_update', 'user_id' => $second->order->user_id, 'item_id' => $second->id, 'changes' => ['order_id' => $first->order_id]],
        ], [$first->order_id, $second->order_id]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame('15.00', $first->order->fresh()->getRawOriginal('amount'));
        $this->assertSame('20.00', $second->order->fresh()->getRawOriginal('amount'));
    }

    public function test_two_valid_orders_subtract_both_quantities(): void
    {
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 10, 'in_stock' => true]);
        $results = $this->race($product, [$this->buyer($product), $this->buyer($product)]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame(2, Order::count());
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_simultaneous_replay_creates_one_order_and_one_event(): void
    {
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);
        $buyer = $this->buyer($product);
        $results = $this->race($product, [$buyer, $buyer]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame($results[0]['redirect'], $results[1]['redirect']);
        $this->assertSame(1, Order::count());
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertSame(1, array_sum(array_column($results, 'events')));
    }

    public function test_parallel_increments_are_not_lost(): void
    {
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 10, 'in_stock' => true]);
        $buyer = $this->buyer($product);
        $operation = ['operation' => 'increment', 'user_id' => $buyer['user_id'], 'product_id' => $product->id];
        $this->race($product, [$operation, $operation]);
        $this->assertSame(3, cart()->count());
    }

    public function test_price_refresh_races_checkout_increment_and_clear(): void
    {
        foreach (['checkout', 'increment', 'clear'] as $operation) {
            $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 10, 'in_stock' => true]);
            $buyer = $this->buyer($product);
            $product->update(['price' => 13000]);
            $other = $operation === 'checkout' ? $buyer : ['operation' => $operation, 'user_id' => $buyer['user_id'], 'product_id' => $product->id];
            $results = $this->race($product, [
                ['operation' => 'refresh_prices', 'user_id' => $buyer['user_id']], $other,
            ]);
            $this->assertSame(200, $results[0]['status']);
            if ($operation === 'checkout') {
                $this->assertContains($results[1]['status'], [200, 422]);
                $ordered = $results[1]['status'] === 200;
                $this->assertSame($ordered ? 9 : 10, $product->fresh()->stock_quantity);
                $this->assertSame($ordered ? 0 : 1, cart()->count());
            } else {
                $this->assertSame(200, $results[1]['status']);
                $this->assertSame($operation === 'clear' ? 0 : 2, cart()->count());
            }
            foreach (cart()->cartItems() as $item) {
                $this->assertSame(1300000, $item->price->minor());
            }
        }
    }

    public function test_increment_during_checkout_is_ordered_and_never_lost(): void
    {
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 10, 'in_stock' => true]);
        $buyer = $this->buyer($product);
        $this->race($product, [$buyer, ['operation' => 'increment', 'user_id' => $buyer['user_id'], 'product_id' => $product->id]]);
        $quantity = Order::sole()->orderItems()->sum('quantity');
        $this->assertSame(2, (int) $quantity + cart()->count());
        $this->assertSame(10 - (int) $quantity, $product->fresh()->stock_quantity);
    }

    public function test_clear_during_checkout_has_a_consistent_result(): void
    {
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 10, 'in_stock' => true]);
        $buyer = $this->buyer($product);
        $results = $this->race($product, [$buyer, ['operation' => 'clear', 'user_id' => $buyer['user_id']]]);
        $this->assertContains($results[0]['status'], [200, 422]);
        $this->assertSame(0, cart()->count());
        $this->assertSame($results[0]['status'] === 200 ? 1 : 0, Order::count());
        $this->assertSame(10 - Order::count(), $product->fresh()->stock_quantity);
    }

    public function test_parallel_increase_and_decrease_do_not_lose_changes(): void
    {
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 10, 'in_stock' => true]);
        $buyer = $this->buyer($product);
        cart()->increment($product);
        $operation = ['operation' => 'increment', 'user_id' => $buyer['user_id'], 'product_id' => $product->id];
        $this->race($product, [$operation + ['by' => 1], $operation + ['by' => -1]]);
        $this->assertSame(2, cart()->count());
    }

    public function test_deadlock_retries_are_bounded_and_do_not_consume_key(): void
    {
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);
        $buyer = $this->buyer($product);
        $attempts = 0;
        Event::listen('eloquent.saving: '.Cart::class, function (Cart $cart) use (&$attempts): void {
            if ($cart->isDirty('checkout_token')) {
                $attempts++;
                throw new PDOException('Deadlock found when trying to get lock', 40001);
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson(route('checkout.store'), $buyer['payload']);
            $this->fail('Expected exhausted retries');
        } catch (PDOException $e) {
            $this->assertSame(40001, $e->getCode());
        }
        $this->assertSame(3, $attempts);
        $this->assertSame(0, Order::count());
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(1, cart()->count());
        $this->assertSame($buyer['payload']['checkout_key'], cart()->checkoutToken());
        Event::assertNotDispatched(OrderCreated::class);
        Event::assertNotDispatched(OrderStatusChanged::class);
    }

    public function test_deadlock_retry_recreates_order_and_dispatches_only_after_commit(): void
    {
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);
        $buyer = $this->buyer($product);
        $attempts = 0;
        Event::listen('eloquent.saving: '.Cart::class, function (Cart $cart) use (&$attempts): void {
            if ($cart->isDirty('checkout_token') && ++$attempts === 1) {
                throw new PDOException('Deadlock found when trying to get lock', 40001);
            }
        });
        $this->postJson(route('checkout.store'), $buyer['payload'])->assertOk();
        $this->assertSame(2, $attempts);
        $this->assertSame(1, Order::count());
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertSame(0, cart()->count());
        Event::assertDispatchedTimes(OrderCreated::class, 1);
        Event::assertDispatchedTimes(OrderStatusChanged::class, 1);
    }
}
