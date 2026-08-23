<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Events\Order\OrderCreated;
use App\Listeners\Order\HandleOrderCreated;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Telegraph as TelegraphClient;
use Domain\Auth\Models\User;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Models\Product;
use Domain\Logging\Models\EventLog;
use Domain\Order\Mail\NewOrderCreated;
use Domain\Order\Models\Order;
use Domain\Profile\Models\Address;
use Domain\Telegram\Models\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Infrastructure\Settings\SiteSettings;
use Tests\TestCase;

/**
 * App\Listeners\Order\HandleOrderCreated::notifyAdminByTelegram() — часть,
 * отвечающая за уведомление в служебную Telegram-группу. По умолчанию в
 * тестовом окружении services.telegram_notify.manage_group не задан (нет
 * TELEGRAM_* в .env.testing) — существующие CheckoutTest/OrderNotificationEmailTest
 * этот код вообще не задевают, конфиг выставляется точечно здесь.
 */
class OrderTelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('ftp');
        Mail::fake();
    }

    public function test_nothing_is_sent_when_manage_group_is_not_configured(): void
    {
        Telegraph::fake();

        $order = Order::create([]);

        $this->callHandler($order);

        Telegraph::assertNothingSent();
        $this->assertSame(0, EventLog::query()->where('event_type', 'order.telegram_notification_failed')->count());
    }

    public function test_message_is_sent_to_manage_group_thread_on_success(): void
    {
        config([
            'services.telegram_notify.manage_group' => '-100123456789',
            'services.telegram_notify.new_order_thread_id' => 42,
        ]);

        Telegraph::fake();

        TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

        $order = Order::create([]);

        $this->callHandler($order);

        Telegraph::assertSentData(TelegraphClient::ENDPOINT_MESSAGE, [
            'chat_id' => '-100123456789',
            'message_thread_id' => 42,
        ], false);
    }

    /**
     * Регрессия: OrderProcess::run() прогоняет один и тот же $order через
     * весь пайплайн; AssignProducts создаёт позиции через
     * $order->orderItems()->createMany(), а OrderItemObserver пересчитывает
     * и сохраняет amount на ОТДЕЛЬНОМ, лениво подгруженном экземпляре Order
     * (через $orderItem->order — belongsTo без кеша), не на этом объекте.
     * Без OrderProcess::run()'s $order->refresh() перед event(new
     * OrderCreated($order)) уведомление уходит с amount = 0, снятым ещё
     * PersistOrder до появления позиций — предыдущие тесты этого файла не
     * ловят баг, т.к. зовут HandleOrderCreated::handle() напрямую на
     * Order::create([]), минуя OrderProcess целиком. Здесь — реальный
     * HTTP-чекаут, тот же путь, что и у настоящего заказа.
     */
    public function test_message_contains_the_correct_order_amount_after_real_checkout(): void
    {
        config([
            'services.telegram_notify.manage_group' => '-100123456789',
            'services.telegram_notify.new_order_thread_id' => 42,
        ]);

        Telegraph::fake();
        Mail::fake();

        TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

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
            'messenger_url' => 'https://t.me/ivan',
        ]);

        Telegraph::assertSentData(TelegraphClient::ENDPOINT_MESSAGE, [
            'text' => 'Сумма: ₽ 12 000',
        ], false);
        Telegraph::assertSentData(TelegraphClient::ENDPOINT_MESSAGE, [
            'text' => 'https://t.me/ivan',
        ], false);
    }

    /**
     * order_customers.messenger_url — nullable для заказов до a94b602
     * (требование ссылки на checkout) или созданных в обход формы (как
     * Order::create([]) в этом файле). Сообщение должно уйти без ссылки,
     * а не с битым <a href=""> или исключением.
     */
    public function test_message_shows_a_placeholder_when_messenger_url_is_missing(): void
    {
        config([
            'services.telegram_notify.manage_group' => '-100123456789',
            'services.telegram_notify.new_order_thread_id' => 42,
        ]);

        Telegraph::fake();

        TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

        $order = Order::create([]);

        $this->callHandler($order);

        Telegraph::assertSentData(TelegraphClient::ENDPOINT_MESSAGE, [
            'text' => 'Мессенджер: </code><i>не указан</i>',
        ], false);
    }

    /**
     * Сбой Telegram-уведомления не должен мешать письму — каналы
     * независимы (у каждого свой try/catch в HandleOrderCreated).
     */
    public function test_telegram_failure_is_logged_and_does_not_prevent_email(): void
    {
        config([
            'services.telegram_notify.manage_group' => '-100123456789',
            'services.telegram_notify.new_order_thread_id' => 42,
        ]);

        Telegraph::fake([
            TelegraphClient::ENDPOINT_MESSAGE => [
                'ok' => false,
                'description' => 'Bad Request: chat not found',
            ],
        ]);

        TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

        $settings = app(SiteSettings::class);
        $settings->notify_email = 'admin@example.test';
        $settings->save();

        $order = Order::create([]);

        $this->callHandler($order);

        $event = EventLog::query()
            ->where('event_type', 'order.telegram_notification_failed')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('error', $event->level);
        $this->assertSame($order->id, $event->context['order_id']);
        $this->assertSame($order->number, $event->context['order_number']);
        $this->assertSame('Bad Request: chat not found', $event->context['error_message']);
        $this->assertStringContainsString("Заказ №{$order->number}", $event->message);

        Mail::assertQueued(NewOrderCreated::class, fn ($mail) => $mail->order->id === $order->id);
    }

    private function callHandler(Order $order): void
    {
        app(HandleOrderCreated::class)->handle(new OrderCreated($order));
    }
}
