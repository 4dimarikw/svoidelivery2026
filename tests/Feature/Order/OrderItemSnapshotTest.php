<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Events\Order\OrderCreated;
use App\Events\Order\OrderStatusChanged;
use App\Listeners\Order\HandleOrderCreated;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Telegraph as TelegraphClient;
use Domain\Auth\Models\User;
use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\Product;
use Domain\Order\Actions\BuildOrderXml;
use Domain\Order\Mail\NewOrderCreated;
use Domain\Order\Models\OrderItem;
use Domain\Profile\Models\Address;
use Domain\Telegram\Models\TelegramBot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class OrderItemSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('ftp');
        Telegraph::fake();
    }

    public function test_sql_and_eloquent_deletion_are_restricted_and_archiving_is_allowed(): void
    {
        $item = OrderItem::factory()->create();
        $before = $item->fresh()->getAttributes();
        $amount = $item->order->getRawOriginal('amount');
        $deletingCalled = false;
        Event::listen('eloquent.deleting: '.Product::class, function () use (&$deletingCalled): void {
            $deletingCalled = true;
        });
        foreach ([fn () => $item->product->delete(), fn () => DB::table('products')->where('id', $item->product_id)->delete()] as $delete) {
            try {
                $delete();
                $this->fail('Expected foreign key restriction');
            } catch (QueryException $e) {
                $this->assertSame(1451, $e->errorInfo[1]);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('product', $e->errors());
            }
            $this->assertSame($before, $item->fresh()->getAttributes());
            $this->assertSame($amount, $item->order->fresh()->getRawOriginal('amount'));
        }
        $this->assertFalse($deletingCalled);
        $item->product->update(['status' => ProductStatus::ARCHIVED]);
        $this->assertSame(ProductStatus::ARCHIVED, $item->product->fresh()->status);
    }

    public function test_product_changes_do_not_change_history_mail_telegram_or_xml(): void
    {
        $product = Product::factory()->create([
            'name' => 'Исходное название', 'brand' => 'Исторический бренд', 'external_code' => 'OLD&<42>',
        ]);
        $item = OrderItem::factory()->for($product)->create();
        $order = $item->order->load(['orderCustomer', 'orderItems', 'deliveryType']);
        $xmlBefore = app(BuildOrderXml::class)->execute($order)->contents;
        $product->update(['name' => 'Изменённое название', 'brand' => 'Новый бренд', 'external_code' => 'NEW-42']);
        $order->refresh()->load(['orderCustomer', 'orderItems', 'deliveryType']);
        $this->assertSame($xmlBefore, app(BuildOrderXml::class)->execute($order)->contents);
        $this->assertSame('OLD&<42>', (string) simplexml_load_string($xmlBefore)->Order->Items->Item->ProductCode);
        $this->actingAs($order->user)->get(route('account.orders.show', $order))
            ->assertOk()->assertSee('Исторический бренд')->assertDontSee('Новый бренд');
        $html = (new NewOrderCreated($order))->render();
        $this->assertStringContainsString('Исторический бренд', $html);
        $this->assertStringNotContainsString('Новый бренд', $html);

        config(['services.telegram_notify.manage_group' => '-100123456789']);
        TelegramBot::create(['token' => 'test-token', 'name' => 'Test', 'username' => 'test_bot']);
        app(HandleOrderCreated::class)->handle(new OrderCreated($order));
        Telegraph::assertSentData(TelegraphClient::ENDPOINT_MESSAGE, ['text' => 'Исторический бренд'], false);
        Http::assertNothingSent();
    }

    public function test_replacing_product_refreshes_snapshot_but_other_edits_do_not(): void
    {
        $item = OrderItem::factory()->create();
        $item->load('product'); // Stale relation must not supply replacement snapshot.
        $replacement = Product::factory()->create(['name' => 'Замена', 'brand' => null]);
        $item->update(['product_id' => $replacement->id]);
        $this->assertSame('Замена', $item->fresh()->product_title);
        $this->assertSame($replacement->external_code, $item->fresh()->product_external_code);
        $replacement->update(['name' => 'Позднее имя', 'brand' => 'Поздний бренд']);
        $item->update(['quantity' => 7, 'price' => 123]);
        $this->assertSame('Замена', $item->fresh()->product_title);
        $this->assertNull($item->fresh()->product_brand);
    }

    public function test_name_fallback_and_html_escaping_use_snapshot(): void
    {
        $product = Product::factory()->create(['name' => 'Пиво & <старое>', 'brand' => null]);
        $item = OrderItem::factory()->for($product)->create();
        $product->update(['name' => 'Новое имя']);
        $this->actingAs($item->order->user)->get(route('account.orders.show', $item->order))
            ->assertOk()->assertSee('Пиво &amp; &lt;старое&gt;', false)->assertDontSee('Новое имя');
    }

    public function test_snapshot_cannot_be_overwritten_by_an_ordinary_model_save(): void
    {
        $item = OrderItem::factory()->create();
        $item->product_name = 'Подмена';
        $this->expectException(LogicException::class);
        $item->save();
    }

    public function test_checkout_uses_fresh_locked_product_for_snapshot(): void
    {
        Event::fake([OrderCreated::class, OrderStatusChanged::class]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => 12000, 'stock_quantity' => 5, 'in_stock' => true]);
        cart()->increment($product);
        $token = cart()->checkoutToken();
        cart()->cartItems();
        $product->update(['name' => 'Имя до checkout', 'brand' => null, 'external_code' => 'AT-CHECKOUT']);
        $this->postJson(route('checkout.store'), [
            'checkout_key' => $token, 'address_id' => $address->id,
            'first_name' => 'Иван', 'last_name' => 'Иванов',
            'phone' => '+79991234567', 'messenger_url' => 'https://t.me/ivan',
        ])->assertOk();
        $this->assertSame('Имя до checkout', OrderItem::sole()->product_name);
        $this->assertSame('AT-CHECKOUT', OrderItem::sole()->product_external_code);
        $this->assertNull(OrderItem::sole()->product_brand);
    }
}
