<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\Order\OrderCreationFailed;
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
use Illuminate\Support\Facades\Event;
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

    public function test_cart_page_renders_checkout_form(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 500]);

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee(__('order.address'));
        $response->assertDontSee(__('order.delivery_type'));
        $response->assertDontSee(__('order.payment_method'));
        $response->assertDontSee('name="delivery_type_id"', false);
        $response->assertDontSee('name="payment_method_id"', false);

        // Плашка ошибки оформления — сосед <x-ui.form> (в sticky-панели
        // итога), не потомок: Alpine-scope идёт по ДОМ-дереву, не по
        // вложенности Blade-компонентов, так что errorFor() из x-data
        // формы там не виден. pages/cart.blade.php зеркалит ошибку в
        // Alpine.store('checkout').error через x-effect на самой форме
        // (resources/js/cart.js), плашка читает уже оттуда.
        $response->assertSee('x-show="$store.checkout.error"', false);
        $response->assertSee('x-text="$store.checkout.error"', false);
        $response->assertSee('$store.checkout.error = errorFor(\'checkout\')', false);
    }

    /**
     * Комментарий адреса («домофон 45») подставляется в поле «Комментарий
     * к заказу» — стартовым значением для выбранного по умолчанию адреса
     * (no-JS путь и SSR перед гидратацией Alpine), и картой id→комментарий
     * для остальных адресов, которую JS использует при переключении радио
     * (resources/views/pages/cart.blade.php, addressComments).
     */
    public function test_default_address_comment_prefills_order_comment_field(): void
    {
        $user = User::factory()->create();
        $defaultAddress = Address::factory()->for($user)->create(['is_default' => true, 'comment' => 'домофон 45']);
        $otherAddress = Address::factory()->for($user)->create(['is_default' => false, 'comment' => 'код 12']);
        $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 500]);

        $response = $this->actingAs($user)->get(route('cart.index'));

        $response->assertOk();
        // Стартовое значение textarea — комментарий адреса по умолчанию.
        $response->assertSee('>домофон 45<', false);

        // Карта id→комментарий (addressComments) — на неё переключается JS
        // при выборе другого адреса, не дожидаясь round-trip на сервер.
        // @js() юникод-экранирует и заворачивает в JSON.parse('...') —
        // разбирать это вручную в тесте хрупко (двойное экранирование
        // кавычек/бэкслэшей под HTML-атрибут), достаточно убедиться, что
        // оба адреса присутствуют как ключи объекта.
        $html = $response->getContent();
        $this->assertStringContainsString('addressComments', $html);
        // @js() экранирует кавычки как escape-последовательность ",
        // не пишет их буквально (иначе они разорвали бы HTML-атрибут
        // x-data="...") — ключ в исходном HTML выглядит как "1":.
        $this->assertStringContainsString("\\u0022{$defaultAddress->id}\\u0022:\\u0022", $html);
        $this->assertStringContainsString("\\u0022{$otherAddress->id}\\u0022:\\u0022", $html);
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
            'phone' => '+79991234567',
            'messenger_url' => 'https://t.me/ivan',
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
            'phone' => '+79991234567',
            'messenger_url' => 'https://t.me/ivan',
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
        // amount тоже проверяем здесь: регрессия на OrderProcess::run() —
        // без её $order->refresh() перед event(new OrderCreated($order))
        // $mail->order->amount оставался бы 0 (снят PersistOrder ещё до
        // появления позиций), хотя в БД (см. $order->fresh() выше) сумма
        // всегда была верной — два разных объекта Order в памяти.
        Mail::assertQueued(
            NewOrderCreated::class,
            fn (NewOrderCreated $mail) => $mail->order->id === $order->id
                && $mail->order->amount->major() === 12000.0
        );
    }

    /**
     * Регрессия: HTML-форма шлёт все значения строками, включая числовой
     * address_id ('1', не 1) — CustomerDTO::fromRequest() раньше падал
     * TypeError'ом на строгой типизации (?int + strict_types=1), потому
     * что не приводил его к int. Телефон тоже проверяем в «сыром» виде
     * с пробелами/скобками — prepareForValidation() должен привести его
     * к каноническому +7XXXXXXXXXX перед сохранением.
     */
    public function test_store_accepts_string_typed_form_fields(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 12000]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'address_id' => (string) $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '8 (999) 123-45-67',
            'messenger_url' => 'https://t.me/ivan',
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('account.orders.show', $order));

        $this->assertDatabaseHas('order_customers', [
            'order_id' => $order->id,
            'phone' => '+79991234567',
            'city' => $address->city,
            'address' => $address->address,
        ]);
    }

    public function test_store_rejects_phone_with_invalid_characters(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 12000]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'address_id' => $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '89873332234оо',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_store_rejects_phone_without_country_prefix(): void
    {
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
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
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
            'phone' => '+79991234567',
            'messenger_url' => 'https://t.me/ivan',
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
            'phone' => '+79991234567',
        ]);

        $response->assertSessionHasErrors('address_id');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_messenger_url_is_required(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 12000]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'address_id' => $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '+79991234567',
        ]);

        $response->assertSessionHasErrors('messenger_url');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_messenger_url_is_saved_on_order_customer(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 12000]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'address_id' => $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '+79991234567',
            'messenger_url' => 'https://max.ru/u/ivan',
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertDatabaseHas('order_customers', [
            'order_id' => $order->id,
            'messenger_url' => 'https://max.ru/u/ivan',
        ]);
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
            'phone' => '+79991234567',
        ]);

        $response->assertSessionHasErrors('address_id');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    public function test_rejects_when_item_went_out_of_stock_after_being_added_to_cart(): void
    {
        Event::fake([OrderCreationFailed::class]);

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
            'phone' => '+79991234567',
            'messenger_url' => 'https://t.me/ivan',
        ]);

        $response->assertSessionHasErrors('checkout');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);

        // Раньше отказ по бизнес-правилу нигде не логировался — единственная
        // точка, где накапливается статистика отказов оформления.
        Event::assertDispatched(OrderCreationFailed::class, function (OrderCreationFailed $event) use ($user): bool {
            $this->assertSame($user->id, $event->userId);
            $this->assertSame('business_rejected', $event->reason);
            $this->assertSame(1, $event->cartItemsCount);

            return true;
        });
    }

    /**
     * Регрессия: CheckProductInStock раньше смотрел только на флаг in_stock,
     * не на фактический остаток — заказ на количество, превышающее склад
     * (другой заказ успел частично раскупить товар между добавлением в
     * корзину и оформлением), проходил, а списание в
     * UpdateProductStockQuantity молча упиралось в max(0, ...).
     */
    public function test_rejects_when_cart_quantity_exceeds_available_stock(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 3, 'price' => 12000]);

        // Остаток просел ниже количества в корзине уже после того, как
        // строка там оказалась — CartManager::increment() не пускает набрать
        // больше остатка с самого начала, но не переклампливает то, что уже
        // лежит в корзине.
        $product->update(['stock_quantity' => 1]);

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'address_id' => $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '+79991234567',
            'messenger_url' => 'https://t.me/ivan',
        ]);

        $response->assertSessionHasErrors('checkout');
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
    }

    /**
     * Регрессия: UpdateProductStockQuantity раньше писал только
     * stock_quantity, оставляя in_stock=true у полностью раскупленного
     * товара — тот расходился с Product::availableStock() и, например,
     * оставался кликабельным в каталоге (см. Domain\Cart\CartManager).
     */
    public function test_store_clears_in_stock_flag_when_order_exhausts_the_stock(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 1, 'in_stock' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 12000]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'address_id' => $address->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'phone' => '+79991234567',
            'messenger_url' => 'https://t.me/ivan',
        ]);

        $fresh = $product->fresh();
        $this->assertSame(0, $fresh->stock_quantity);
        $this->assertFalse($fresh->in_stock);
    }
}
