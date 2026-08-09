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
        $response->assertJson(['quantity' => 1, 'count' => 1, 'amount' => '₽ 100', 'lineAmount' => '₽ 100']);
    }

    public function test_increase_json_response_reports_the_updated_line_amount(): void
    {
        // Регрессия: сумма строки в корзине (cart-line.blade.php) раньше не
        // реагировала на +/- в степпере, потому что lineAmount в JSON-ответе
        // вообще не было.
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5, 'in_stock' => true]);

        $this->actingAs($user)->postJson(route('cart.increase', $product));
        $response = $this->actingAs($user)->postJson(route('cart.increase', $product));

        $response->assertOk();
        $response->assertJson(['quantity' => 2, 'lineAmount' => '₽ 200']);
    }

    public function test_decrease_json_response_reports_the_updated_line_amount(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5, 'in_stock' => true]);

        $this->actingAs($user)->postJson(route('cart.increase', $product));
        $this->actingAs($user)->postJson(route('cart.increase', $product));
        $response = $this->actingAs($user)->patchJson(route('cart.decrease', $product));

        $response->assertOk();
        $response->assertJson(['quantity' => 1, 'lineAmount' => '₽ 100']);
    }

    public function test_decrease_to_zero_reports_a_null_line_amount(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5, 'in_stock' => true]);

        $this->actingAs($user)->postJson(route('cart.increase', $product));
        $response = $this->actingAs($user)->patchJson(route('cart.decrease', $product));

        $response->assertOk();
        $response->assertJson(['quantity' => 0, 'lineAmount' => null]);
    }

    public function test_destroy_json_response_reports_a_null_line_amount(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5, 'in_stock' => true]);

        $this->actingAs($user)->postJson(route('cart.increase', $product));
        $response = $this->actingAs($user)->deleteJson(route('cart.destroy', $product));

        $response->assertOk();
        $response->assertJson(['lineAmount' => null]);
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

    public function test_index_with_items_shows_summary_and_continue_shopping_cta(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 100]);

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee(__('account.cart.items'));
        $response->assertSee(__('account.cart.total_label'));
        $response->assertSee(__('account.cart.checkout'));
        $response->assertSee(route('checkout.index'), false);
        $response->assertSee(__('account.cart.continue'));
        $response->assertSee(route('home'), false);
    }

    public function test_index_empty_shows_empty_state_and_hides_summary_behind_x_cloak(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee(__('account.cart.empty'));

        // Панель со списком/итогом остаётся в разметке (server-render не
        // условный на пустоту, а параллельный x-show — см. комментарий в
        // pages/cart.blade.php), но помечена x-cloak: [x-cloak]{display:none}
        // (app.css) скрывает её и без JS, до и после гидратации Alpine.
        $content = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/x-show="\$store\.cart\.count > 0"[^>]*x-cloak/',
            $content
        );
    }
}
