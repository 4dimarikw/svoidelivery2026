<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\Order\OrderCreated;
use App\Events\Order\OrderStatusChanged;
use Domain\Auth\Models\User;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Profile\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductPurchaseEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Event::fake([OrderCreated::class, OrderStatusChanged::class]);
        $this->actingAs(User::factory()->create());
    }

    public static function unavailableStates(): array
    {
        return array_map(fn ($state) => [$state], ['draft', 'archived', 'category', 'manufacturer']);
    }

    private function disable(Product $product, string $state): void
    {
        if (in_array($state, ['draft', 'archived'], true)) {
            $product->update(['status' => ProductStatus::from($state)]);
        } else {
            $product->{$state}->update(['is_active' => false]);
        }
    }

    private function product(): Product
    {
        return Product::factory()->for(Manufacturer::factory())->create([
            'price' => 12000, 'in_stock' => true, 'stock_quantity' => 5,
        ]);
    }

    #[DataProvider('unavailableStates')]
    public function test_direct_add_is_rejected_without_creating_a_cart(string $state): void
    {
        $product = $this->product();
        $this->disable($product, $state);
        $this->postJson(route('cart.increase', $product))->assertUnprocessable();
        $this->post(route('cart.increase', $product))->assertRedirect()
            ->assertSessionHas('status', 'cart-item-unavailable');
        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame(5, $product->fresh()->availableStock());
        $this->get(route('product.show', $product))->assertNotFound();
        $this->assertFalse(Product::query()->visibleOnStorefront()->whereKey($product->id)->exists());
        $this->get(route('home'))->assertOk()->assertViewHas('products',
            fn ($products) => ! $products->contains('id', $product->id));
    }

    #[DataProvider('unavailableStates')]
    public function test_unavailability_after_adding_rejects_checkout_and_preserves_cart(string $state): void
    {
        $product = $this->product();
        cart()->increment($product);
        $token = cart()->checkoutToken();
        cart()->cartItems(); // Intentionally warm product and reference caches.
        $address = Address::factory()->for(auth()->user())->create();
        $this->disable($product, $state);
        $cartBefore = Cart::sole()->getAttributes();
        $itemBefore = CartItem::sole()->getAttributes();
        $productBefore = $product->fresh()->getAttributes();

        $this->postJson(route('cart.increase', $product))->assertUnprocessable();
        $payload = [
            'checkout_key' => $token, 'address_id' => $address->id,
            'first_name' => 'Иван', 'last_name' => 'Иванов',
            'phone' => '+79991234567', 'messenger_url' => 'https://t.me/ivan',
        ];
        $message = __('order.errors.product_unavailable', ['title' => $product->name]);
        $this->postJson(route('checkout.store'), $payload)->assertUnprocessable()
            ->assertJsonPath('errors.checkout.0', $message);
        $this->post(route('checkout.store'), $payload)->assertRedirect()
            ->assertSessionHasErrors(['checkout' => $message]);

        $this->assertSame($cartBefore, Cart::sole()->getAttributes());
        $this->assertSame($itemBefore, CartItem::sole()->getAttributes());
        $this->assertSame($productBefore, $product->fresh()->getAttributes());
        foreach (['orders', 'order_items', 'order_customers'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Event::assertNotDispatched(OrderCreated::class);
        Event::assertNotDispatched(OrderStatusChanged::class);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        cart()->remove($product);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_published_sold_out_product_remains_visible_but_cannot_be_bought(): void
    {
        $product = $this->product();
        $product->update(['stock_quantity' => 0, 'in_stock' => false]);
        $this->get(route('product.show', $product))->assertOk();
        $this->assertTrue(Product::query()->visibleOnStorefront()->whereKey($product->id)->exists());
        $this->postJson(route('cart.increase', $product))->assertUnprocessable();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_optional_manufacturer_and_inactive_container_do_not_block_purchase(): void
    {
        $product = Product::factory()->for(Container::factory())->create(['manufacturer_id' => null, 'stock_quantity' => 5, 'in_stock' => true]);
        $product->container->update(['is_active' => false]);
        $this->get(route('product.show', $product))->assertOk();
        $this->postJson(route('cart.increase', $product))->assertOk();
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_successful_checkout_replay_still_works_after_unpublishing(): void
    {
        $product = $this->product();
        cart()->increment($product);
        $key = cart()->checkoutToken();
        $address = Address::factory()->for(auth()->user())->create();
        $first = $this->postJson(route('checkout.store'), [
            'checkout_key' => $key, 'address_id' => $address->id,
            'first_name' => 'Иван', 'last_name' => 'Иванов',
            'phone' => '+79991234567', 'messenger_url' => 'https://t.me/ivan',
        ])->assertOk();
        $this->disable($product, 'draft');
        $this->postJson(route('checkout.store'), ['checkout_key' => $key])->assertOk()
            ->assertHeader('X-Redirect', $first->headers->get('X-Redirect'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(4, $product->fresh()->stock_quantity);
        Event::assertDispatchedTimes(OrderCreated::class, 1);
    }
}
