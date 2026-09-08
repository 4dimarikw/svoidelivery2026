<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Events\Order\OrderCreated;
use App\Events\Order\OrderStatusChanged;
use Domain\Auth\Models\User;
use Domain\Cart\Models\Cart;
use Domain\Catalog\Models\Product;
use Domain\Order\Models\Order;
use Domain\Profile\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Address $address;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Event::fake([OrderCreated::class, OrderStatusChanged::class]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->address = Address::factory()->for($user)->create();
        $this->product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);
        cart()->increment($this->product);
        $this->payload = [
            'checkout_key' => cart()->checkoutToken(),
            'address_id' => $this->address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '+79991234567',
            'messenger_url' => 'https://t.me/ivan',
        ];
    }

    public function test_replay_returns_order_before_validating_address_and_preserves_new_cart(): void
    {
        $first = $this->postJson(route('checkout.store'), $this->payload)->assertOk();
        $order = Order::sole();
        $this->assertSame(4, $this->product->fresh()->stock_quantity);
        $this->address->delete();
        cart()->increment($this->product);
        $newToken = cart()->checkoutToken();

        $this->postJson(route('checkout.store'), $this->payload)->assertOk()
            ->assertHeader('X-Redirect', $first->headers->get('X-Redirect'));
        $this->post(route('checkout.store'), ['checkout_key' => $this->payload['checkout_key']])
            ->assertRedirect(route('account.orders.show', $order));

        $this->assertSame(1, Order::count());
        $this->assertSame(1, cart()->count());
        $this->assertSame(4, $this->product->fresh()->stock_quantity);
        $this->assertSame($newToken, cart()->checkoutToken());
        $this->assertNotSame($this->payload['checkout_key'], $newToken);
        Event::assertDispatchedTimes(OrderCreated::class, 1);
        Event::assertDispatchedTimes(OrderStatusChanged::class, 1);
    }

    public function test_cart_tabs_share_hidden_key(): void
    {
        foreach ([1, 2] as $tab) {
            $this->get(route('cart.index'))->assertOk()
                ->assertSee('name="checkout_key" value="'.$this->payload['checkout_key'].'"', false);
        }
    }

    public function test_missing_invalid_stale_and_foreign_keys_are_rejected(): void
    {
        $this->postJson(route('checkout.store'), array_diff_key($this->payload, ['checkout_key' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('checkout_key');
        $this->postJson(route('checkout.store'), ['checkout_key' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('checkout_key');
        $this->postJson(route('checkout.store'), ['checkout_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('checkout');
        $this->postJson(route('checkout.store'), $this->payload)->assertOk();
        $this->actingAs(User::factory()->create());
        $this->postJson(route('checkout.store'), $this->payload)
            ->assertUnprocessable()->assertJsonValidationErrors('checkout');
        $this->assertSame(1, Order::count());
    }

    public function test_validation_error_does_not_consume_key(): void
    {
        $this->postJson(route('checkout.store'), ['checkout_key' => $this->payload['checkout_key']])
            ->assertUnprocessable()->assertJsonValidationErrors('address_id');
        $this->assertSame($this->payload['checkout_key'], cart()->checkoutToken());
        $this->postJson(route('checkout.store'), $this->payload)->assertOk();
    }

    public function test_checkout_discards_preloaded_cart_and_product_models(): void
    {
        cart()->cartItems();
        $this->product->update(['stock_quantity' => 0, 'in_stock' => false]);
        $this->postJson(route('checkout.store'), $this->payload)
            ->assertUnprocessable()->assertJsonValidationErrors('checkout');
        $this->assertSame(0, Order::count());
        $this->assertSame(1, cart()->count());
        $this->assertSame($this->payload['checkout_key'], cart()->checkoutToken());
    }

    public function test_failure_after_order_and_cart_changes_rolls_everything_back(): void
    {
        Event::listen('eloquent.saving: '.Cart::class, function (Cart $cart): void {
            if ($cart->isDirty('checkout_token') && $cart->getOriginal('checkout_token') !== null) {
                throw new RuntimeException('Simulated rotation failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson(route('checkout.store'), $this->payload);
            $this->fail('Expected transaction failure');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated rotation failure', $e->getMessage());
        }

        $this->assertSame(0, Order::count());
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_customers', 0);
        $this->assertSame(5, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, cart()->count());
        $this->assertSame($this->payload['checkout_key'], cart()->checkoutToken());
        Event::assertNotDispatched(OrderCreated::class);
        Event::assertNotDispatched(OrderStatusChanged::class);
    }
}
