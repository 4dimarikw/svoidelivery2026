<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Models\Product;
use Domain\Order\Mail\NewOrderCreated;
use Domain\Order\Models\DeliveryType;
use Domain\Order\Models\Order;
use Domain\Order\Models\PaymentMethod;
use Domain\Profile\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Infrastructure\Settings\SiteSettings;
use Tests\TestCase;

/**
 * delivery_types/payment_methods сидируются прямо в миграциях (см.
 * database/migrations/2026_08_09_1000{00,01}_*) — RefreshDatabase их
 * пересоздаёт перед каждым тестом, отдельный сидинг тут не нужен.
 *
 * Публичное оформление заказа не даёт выбрать способ доставки/оплаты —
 * оба фиксированы сервером (config/order.php, OrderController::store()) на
 * «Служба доставки»/«При получении». Раз доставка всегда с адресом,
 * address_id везде обязателен.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Успешный чекаут вызывает UploadOrderToFTP::execute() синхронно
        // (OrderProcess::run()) — без фейкового диска это либо реальный (и
        // недоступный в тестах) FTP с 3 retry-попытками и задержками, либо,
        // при QUEUE_CONNECTION=sync (phpunit.xml), синхронный запуск job'а,
        // чей RuntimeException при провале пробрасывается прямо в тест.
        Storage::fake('ftp');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('login'));
    }

    public function test_index_redirects_to_cart_when_cart_is_empty(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'));
    }

    public function test_index_renders_checkout_page(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 500]);

        $response = $this->actingAs($user)->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee(__('order.title'));
        $response->assertSee(__('order.address'));
        $response->assertDontSee(__('order.delivery_type'));
        $response->assertDontSee(__('order.payment_method'));
        $response->assertDontSee('name="delivery_type_id"', false);
        $response->assertDontSee('name="payment_method_id"', false);
    }

    public function test_happy_path_creates_order_with_saved_address(): void
    {
        Mail::fake();

        $settings = app(SiteSettings::class);
        $settings->notify_email = 'admin@example.test';
        $settings->save();

        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 12000]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'address_id' => $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '9991234567',
            'comment' => 'Позвоните за час',
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('account.orders.show', $order));

        // Способ доставки/оплаты — фиксированные дефолты сервера, не то,
        // что мог бы прислать клиент.
        $this->assertSame(DeliveryType::default()->id, $order->delivery_type_id);
        $this->assertSame(PaymentMethod::default()->id, $order->payment_method_id);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // order_customers — снимок адреса на момент заказа, не FK на Address.
        $this->assertDatabaseHas('order_customers', [
            'order_id' => $order->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '9991234567',
            'city' => $address->city,
            'address' => $address->address,
        ]);

        // OrderItemObserver → UpdateAmountOrder — сумма не проставляется
        // вручную в AssignProducts, а пересчитывается по позициям.
        $this->assertSame('12000.00', number_format($order->fresh()->amount->major(), 2, '.', ''));
        $this->assertSame('pending', $order->fresh()->status->value());

        $this->assertSame(0, CartItem::query()->where('cart_id', $cart->id)->count());
        $this->assertSame(4, $product->fresh()->stock_quantity);

        // OrderCreated → App\Listeners\Order\HandleOrderCreated →
        // UploadOrderToFTP — проверяем, что цепочка не разорвана.
        $this->assertCount(1, Storage::disk('ftp')->allFiles(config('order.ftp_upload.dir')));

        // Та же цепочка → notifyAdmin() — письмо ставится в очередь, не
        // отправляется синхронно (см. HandleOrderCreated::notifyAdmin()).
        Mail::assertQueued(NewOrderCreated::class, fn (NewOrderCreated $mail) => $mail->order->id === $order->id);
    }

    public function test_store_ignores_client_supplied_delivery_and_payment(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 12000]);

        // Подсовываем чужие id (Самовывоз/Онлайн) — контроллер их читать не должен.
        $otherDeliveryType = DeliveryType::query()->where('title', '!=', config('order.default_delivery_type'))->firstOrFail();
        $otherPaymentMethod = PaymentMethod::query()->where('title', '!=', config('order.default_payment_method'))->firstOrFail();

        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_type_id' => $otherDeliveryType->id,
            'payment_method_id' => $otherPaymentMethod->id,
            'address_id' => $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '9991234567',
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(DeliveryType::default()->id, $order->delivery_type_id);
        $this->assertSame(PaymentMethod::default()->id, $order->payment_method_id);
    }

    public function test_address_is_required(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 12000]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '9991234567',
        ]);

        $response->assertSessionHasErrors('address_id');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_cannot_use_another_users_address(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $theirAddress = Address::factory()->for($other)->create();

        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 12000]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'address_id' => $theirAddress->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '9991234567',
        ]);

        $response->assertSessionHasErrors('address_id');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_rejects_amount_below_minimum_for_delivery_with_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 500]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'address_id' => $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '9991234567',
        ]);

        $response->assertSessionHasErrors('checkout');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
        $this->assertSame(1, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_rejects_when_item_went_out_of_stock_after_being_added_to_cart(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 12000]);

        // Товар закончился уже после того, как оказался в корзине —
        // CartManager::increment() не пускает добавить его так с самого начала.
        $product->update(['in_stock' => false]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'address_id' => $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '9991234567',
        ]);

        $response->assertSessionHasErrors('checkout');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }
}
