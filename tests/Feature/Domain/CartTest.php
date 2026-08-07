<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use Domain\Auth\Models\User;
use Domain\Cart\CartManager;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_has_an_empty_cart_and_manager_is_a_no_op(): void
    {
        $manager = app(CartManager::class);
        $product = Product::factory()->create();

        $this->assertSame(0, $manager->count());
        $this->assertTrue($manager->amount()->isZero());
        $this->assertFalse($manager->has($product));
        $this->assertSame(0, $manager->quantityOf($product));

        $manager->increment($product);

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_increment_creates_a_cart_and_item_then_increments_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5, 'in_stock' => true]);
        $this->actingAs($user);

        $manager = app(CartManager::class);
        $manager->increment($product);
        $manager->increment($product);

        $this->assertSame(2, $manager->quantityOf($product));
        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
        $this->assertSame(
            1,
            CartItem::query()->where('product_id', $product->id)->count()
        );
    }

    public function test_increment_clamps_to_stock_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 2, 'in_stock' => true]);
        $this->actingAs($user);

        $manager = app(CartManager::class);
        $manager->increment($product, 5);

        $this->assertSame(2, $manager->quantityOf($product));
    }

    public function test_increment_does_nothing_when_product_is_out_of_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10, 'in_stock' => false]);
        $this->actingAs($user);

        app(CartManager::class)->increment($product);

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_decrement_removes_the_item_once_quantity_reaches_zero(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5, 'in_stock' => true]);
        $this->actingAs($user);

        $manager = app(CartManager::class);
        $manager->increment($product);
        $manager->decrement($product);

        $this->assertSame(0, $manager->quantityOf($product));
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_decrement_on_an_absent_item_is_a_safe_no_op(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($user);

        app(CartManager::class)->decrement($product);

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_remove_deletes_the_item_regardless_of_quantity(): void
    {
        // Крестик-удаление на странице корзины (cart-line.blade.php) — снимает
        // строку целиком независимо от количества, в отличие от decrement().
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5, 'in_stock' => true]);
        $this->actingAs($user);

        $manager = app(CartManager::class);
        $manager->increment($product, 3);
        $manager->remove($product);

        $this->assertSame(0, $manager->quantityOf($product));
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_amount_sums_price_times_quantity_across_items(): void
    {
        $user = User::factory()->create();
        $productA = Product::factory()->create(['price' => 100, 'stock_quantity' => 5, 'in_stock' => true]);
        $productB = Product::factory()->create(['price' => 250, 'stock_quantity' => 5, 'in_stock' => true]);
        $this->actingAs($user);

        $manager = app(CartManager::class);
        $manager->increment($productA, 2);
        $manager->increment($productB, 1);

        // 100 * 2 + 250 * 1 = 450 рублей = 45000 копеек.
        $this->assertSame(45000, $manager->amount()->minor());
    }

    public function test_truncate_removes_only_the_current_users_cart_items(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->count(2)->create(['cart_id' => $cart->id]);

        $otherCart = Cart::factory()->create(['user_id' => $other->id]);
        CartItem::factory()->create(['cart_id' => $otherCart->id]);

        $this->actingAs($user);
        app(CartManager::class)->truncate();

        $this->assertSame(0, CartItem::query()->where('cart_id', $cart->id)->count());
        $this->assertSame(1, CartItem::query()->where('cart_id', $otherCart->id)->count());
    }

    public function test_deleting_the_user_cascades_to_their_cart_and_items(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $item = CartItem::factory()->create(['cart_id' => $cart->id]);

        $user->delete();

        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_deleting_the_product_cascades_to_cart_items(): void
    {
        $product = Product::factory()->create();
        $item = CartItem::factory()->create(['product_id' => $product->id]);

        $product->delete();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }
}
