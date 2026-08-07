<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cart.index'))->assertRedirect(route('login'));
    }

    public function test_increase_adds_then_increments_the_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5, 'in_stock' => true]);

        $this->actingAs($user)->post(route('cart.increase', $product));
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)->post(route('cart.increase', $product));
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_increase_clamps_to_stock_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 2, 'in_stock' => true]);

        $this->actingAs($user)->post(route('cart.increase', $product));
        $this->actingAs($user)->post(route('cart.increase', $product));
        $this->actingAs($user)->post(route('cart.increase', $product));

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_decrease_decrements_then_removes_the_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5, 'in_stock' => true]);

        $this->actingAs($user)->post(route('cart.increase', $product));
        $this->actingAs($user)->post(route('cart.increase', $product));
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($user)->patch(route('cart.decrease', $product));
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)->patch(route('cart.decrease', $product));
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_destroy_removes_the_item_regardless_of_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5, 'in_stock' => true]);

        $this->actingAs($user)->post(route('cart.increase', $product));
        $this->actingAs($user)->post(route('cart.increase', $product));
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($user)->delete(route('cart.destroy', $product));

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_increase_json_response_reports_quantity_count_and_amount(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5, 'in_stock' => true]);

        $response = $this->actingAs($user)->postJson(route('cart.increase', $product));

        $response->assertOk();
        $response->assertJson(['quantity' => 1, 'count' => 1, 'amount' => '₽ 100']);
    }

    public function test_index_lists_only_the_current_users_cart_items(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Product::factory()->create();
        $theirs = Product::factory()->create();

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $mine->id]);

        $otherCart = Cart::factory()->create(['user_id' => $other->id]);
        CartItem::factory()->create(['cart_id' => $otherCart->id, 'product_id' => $theirs->id]);

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response->assertOk();
        $response->assertViewHas('cartItems', fn ($cartItems) => $cartItems->pluck('product_id')->all() === [$mine->id]);
    }

    public function test_clear_empties_only_the_current_users_cart(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->count(2)->create(['cart_id' => $cart->id]);

        $otherCart = Cart::factory()->create(['user_id' => $other->id]);
        CartItem::factory()->create(['cart_id' => $otherCart->id]);

        $response = $this->actingAs($user)->delete(route('cart.clear'));

        $response->assertRedirect(route('cart.index'));
        $this->assertSame(0, CartItem::query()->where('cart_id', $cart->id)->count());
        $this->assertSame(1, CartItem::query()->where('cart_id', $otherCart->id)->count());
    }
}
