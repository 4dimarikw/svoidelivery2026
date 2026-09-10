<?php

namespace Tests\Feature\Order;

use Domain\Cart\Actions\UpdateCartPrices;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Infrastructure\Jobs\UpdateCartPricesJob;
use Tests\TestCase;

class UpdateCartPricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_batches_refresh_current_prices_without_changing_tokens_or_quantities(): void
    {
        Http::preventStrayRequests();
        Bus::fake();
        $product = Product::factory()->create(['price' => '12.50', 'in_stock' => false]);
        $ids = [];
        for ($i = 0; $i < 101; $i++) {
            $cart = Cart::factory()->create();
            $cart->checkout_token = (string) Str::uuid();
            $cart->save();
            $ids[] = $cart->id;
            CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 3, 'price' => 20]);
        }
        $tokens = Cart::pluck('checkout_token', 'id')->all();
        $action = app(UpdateCartPrices::class);
        (new UpdateCartPricesJob)->handle($action);
        $this->assertSame(100, CartItem::where('price', '12.50')->count());
        Bus::assertDispatched(UpdateCartPricesJob::class, fn ($job) => $job->afterId === $ids[99]);
        (new UpdateCartPricesJob($ids[99]))->handle($action);
        (new UpdateCartPricesJob)->handle($action);
        $this->assertSame(101, CartItem::where('price', '12.50')->count());
        $this->assertSame(303, (int) CartItem::sum('quantity'));
        $this->assertSame($tokens, Cart::pluck('checkout_token', 'id')->all());
        CartItem::where('cart_id', $ids[0])->delete();
        $action->handle($ids[0]);
        Cart::whereKey($ids[1])->delete();
        $action->handle($ids[1]);
        $this->assertSame(0, CartItem::where('cart_id', $ids[0])->count());
        $this->assertNull(Cart::find($ids[1]));
    }
}
