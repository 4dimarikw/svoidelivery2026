<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use Domain\Auth\Models\User;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Product;
use Domain\Profile\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Domain\Order\Processes\TermsOrder::assertPackMultiplicity() — логистика
 * короба/паллеты требует, чтобы сумма количества товаров категорий
 * beer/mead/cider/non_alcoholic (categories.code, не slug) в заказе была
 * кратна 12 или 20. Тара конкретного товара значения не имеет — только
 * принадлежность категории.
 */
class PackMultiplicityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Тот же повод, что у CheckoutTest — happy path гоняет
        // UploadOrderToFTP::execute() синхронно.
        Storage::fake('ftp');
    }

    private function checkout(User $user, array $overrides = []): TestResponse
    {
        $address = Address::factory()->for($user)->create();

        return $this->actingAs($user)->post(route('checkout.store'), array_merge([
            'address_id' => $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '+79991234567',
        ], $overrides));
    }

    private function addToCart(User $user, Product $product, int $quantity): void
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $product->price,
        ]);
    }

    /**
     * Категории с этими code уже засеяны миграцией
     * 2026_08_03_100200_seed_category_registry_from_config — RefreshDatabase
     * пересоздаёт их перед каждым тестом, создавать заново нельзя
     * (categories.code уникален).
     */
    private function categoryByCode(string $code): Category
    {
        return Category::query()->where('code', $code)->firstOrFail();
    }

    public function test_order_of_12_beer_items_is_accepted(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryByCode('beer');
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 1000, 'stock_quantity' => 20, 'in_stock' => true]);

        $this->addToCart($user, $product, 12);

        $this->checkout($user);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
    }

    public function test_order_of_20_beer_items_is_accepted(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryByCode('beer');
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 1000, 'stock_quantity' => 20, 'in_stock' => true]);

        $this->addToCart($user, $product, 20);

        $this->checkout($user);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
    }

    public function test_order_of_7_beer_items_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryByCode('beer');
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 2000, 'stock_quantity' => 10, 'in_stock' => true]);

        $this->addToCart($user, $product, 7);

        $response = $this->checkout($user);

        // Проверяем именно текст правила про кратность, а не то, что заказ
        // упал на min_amount_1 (у которого тоже 'checkout' — ключ ошибки).
        $response->assertSessionHasErrors(['checkout' => __('order.errors.pack_multiplicity', ['quantity' => 7])]);
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    /**
     * Сумма считается по категории целиком, не по отдельной позиции —
     * позиции внутри заказа могут быть добавлены и по 1 шт.
     */
    public function test_quantity_is_summed_across_categories_and_line_items(): void
    {
        $user = User::factory()->create();
        $beer = $this->categoryByCode('beer');
        $cider = $this->categoryByCode('cider');
        $beerProduct = Product::factory()->create(['category_id' => $beer->id, 'price' => 1000, 'stock_quantity' => 10, 'in_stock' => true]);
        $ciderProduct = Product::factory()->create(['category_id' => $cider->id, 'price' => 1000, 'stock_quantity' => 10, 'in_stock' => true]);

        $this->addToCart($user, $beerProduct, 5);
        $this->addToCart($user, $ciderProduct, 7);

        $this->checkout($user);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
    }

    public function test_category_outside_the_list_is_not_restricted(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryByCode('souvenirs');
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 2000, 'stock_quantity' => 10, 'in_stock' => true]);

        $this->addToCart($user, $product, 7);

        $this->checkout($user);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
    }

    /**
     * Тара (container_id) не участвует в правиле вообще — только
     * принадлежность категории.
     */
    public function test_rule_ignores_product_container(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryByCode('mead');
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'container_id' => null,
            'price' => 1000,
            'stock_quantity' => 20,
            'in_stock' => true,
        ]);

        $this->addToCart($user, $product, 12);

        $this->checkout($user);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
    }

    public function test_non_alcoholic_category_uses_code_not_slug(): void
    {
        $user = User::factory()->create();
        // code non_alcoholic, slug non-alcoholic — правило матчит по code.
        $category = $this->categoryByCode('non_alcoholic');
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 2000, 'stock_quantity' => 10, 'in_stock' => true]);

        $this->addToCart($user, $product, 7);

        $response = $this->checkout($user);

        $response->assertSessionHasErrors(['checkout' => __('order.errors.pack_multiplicity', ['quantity' => 7])]);
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }
}
