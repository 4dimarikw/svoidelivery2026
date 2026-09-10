<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Events\Order\OrderCreated;
use App\Events\Order\OrderStatusChanged;
use App\MoonShine\Resources\DeliveryType\Pages\DeliveryTypeFormPage;
use App\MoonShine\Resources\OrderItem\Pages\OrderItemFormPage;
use App\MoonShine\Resources\Product\Pages\ProductFormPage;
use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use Domain\Order\Models\OrderItem;
use Domain\Profile\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use ReflectionMethod;
use Support\Rules\DecimalPrice;
use Support\ValueObjects\Price;
use Tests\TestCase;

class PriceValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('ftp');
        Event::fake([OrderCreated::class, OrderStatusChanged::class]);
    }

    public function test_form_rule_rejects_bad_price_quantity_and_references(): void
    {
        $item = OrderItem::factory()->create();
        $wrapper = $this->createMock(DataWrapperContract::class);
        $wrapper->method('getOriginal')->willReturn($item);
        $page = app(OrderItemFormPage::class);
        $rules = (new ReflectionMethod($page, 'rules'))->invoke($page, $wrapper);
        $valid = ['price' => '12,50', 'quantity' => 1, 'product_id' => $item->product_id, 'order_id' => $item->order_id];
        $this->assertFalse(Validator::make($valid, $rules)->fails());
        foreach ([['price', '0'], ['price', 'abc'], ['price', []], ['price', '-1'], ['price', '1e2'], ['price', '10000000000'],
            ['quantity', 0], ['quantity', -1], ['quantity', '1.5'], ['quantity', '4294967296'], ['product_id', 99999999], ['order_id', 99999999]] as [$key, $value]) {
            $validator = Validator::make(array_replace($valid, [$key => $value]), $rules);
            $this->assertTrue($validator->fails());
            $this->assertTrue($validator->errors()->has($key));
        }
        $this->assertFalse(Validator::make(['price' => '0'], ['price' => new DecimalPrice])->fails());
    }

    public function test_cast_writes_exact_maximum_and_rejects_oversized_price_object(): void
    {
        $product = Product::factory()->create(['price' => '9 999 999 999,99']);
        $this->assertSame('9999999999.99', $product->fresh()->getRawOriginal('price'));
        $product->price = '12,50';
        $product->save();
        $this->assertSame('12.50', $product->fresh()->getRawOriginal('price'));
        $this->expectException(\InvalidArgumentException::class);
        $product->price = Price::fromMinor(Price::MAX_MINOR + 1);
    }

    public function test_line_overflow_is_rejected_before_insert(): void
    {
        $item = OrderItem::factory()->make(['price' => '9999999999.99', 'quantity' => 4294967295]);
        $creating = false;
        Event::listen('eloquent.creating: '.OrderItem::class, function () use (&$creating): void {
            $creating = true;
        });
        try {
            $item->save();
            $this->fail('Expected overflow');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('price', $e->errors());
        }
        $this->assertFalse($creating);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_total_overflow_leaves_both_orders_and_item_unchanged(): void
    {
        $item = OrderItem::factory()->create(['price' => '1.00', 'quantity' => 1]);
        $large = OrderItem::factory()->create(['price' => '9999999999.99', 'quantity' => 1]);
        $before = $item->fresh()->getAttributes();
        try {
            $item->update(['order_id' => $large->order_id]);
            $this->fail('Expected overflow');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('price', $e->errors());
        }
        $this->assertSame($before, $item->fresh()->getAttributes());
        $this->assertSame('1.00', $item->fresh()->order->getRawOriginal('amount'));
        $this->assertSame('9999999999.99', $large->order->fresh()->getRawOriginal('amount'));
    }

    public function test_checkout_overflow_returns_422_and_preserves_cart_and_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $address = Address::factory()->for($user)->create();
        $product = Product::factory()->create(['price' => '9999999999.99', 'stock_quantity' => 5, 'in_stock' => true]);
        cart()->increment($product, 2);
        $token = cart()->checkoutToken();
        $this->postJson(route('checkout.store'), [
            'checkout_key' => $token, 'address_id' => $address->id, 'first_name' => 'Иван', 'last_name' => 'Иванов',
            'phone' => '+79991234567', 'messenger_url' => 'https://t.me/ivan',
        ])->assertUnprocessable()->assertJsonValidationErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, cart()->count());
        $this->assertSame($token, cart()->checkoutToken());
        $this->assertSame(5, $product->fresh()->stock_quantity);
        Event::assertNotDispatched(OrderCreated::class);
    }

    public function test_product_and_delivery_form_rules_accept_zero_and_comma_but_reject_bad_values(): void
    {
        $wrapper = $this->createMock(DataWrapperContract::class);
        foreach ([ProductFormPage::class,
            DeliveryTypeFormPage::class] as $class) {
            $page = app($class);
            $rules = (new ReflectionMethod($page, 'rules'))->invoke($page, $wrapper);
            foreach (['0', '12,50', '9999999999.99'] as $value) {
                $this->assertFalse(Validator::make(['price' => $value], ['price' => $rules['price']])->fails());
            }
            foreach (['abc', '1e2', '10000000000', '-1', '12.345', []] as $value) {
                $this->assertTrue(Validator::make(['price' => $value], ['price' => $rules['price']])->fails());
            }
        }
    }

    public function test_admin_http_rejects_bad_prices_and_overflow_and_saves_comma(): void
    {
        $admin = MoonshineUser::create([
            'email' => 'price-test@example.test', 'name' => 'Price test', 'password' => bcrypt('test-password'),
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
        $this->actingAs($admin, 'moonshine');
        $item = OrderItem::factory()->create(['price' => '10.00', 'quantity' => 2]);
        $url = '/admin/resource/order-item-resource/crud/'.$item->id;
        $payload = ['price' => 'abc', 'quantity' => 2, 'product_id' => $item->product_id, 'order_id' => $item->order_id];
        foreach (['abc', '0', '12.345', '9999999999.99'] as $price) {
            $this->putJson($url, array_replace($payload, ['price' => $price]), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertUnprocessable()->assertJsonValidationErrors('price');
            $this->assertSame('10.00', $item->fresh()->getRawOriginal('price'));
            $this->assertSame('20.00', $item->order->fresh()->getRawOriginal('amount'));
        }
        $this->putJson($url, array_replace($payload, ['price' => '12,50']), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $this->assertSame('12.50', $item->fresh()->getRawOriginal('price'));
        $this->assertSame('25.00', $item->order->fresh()->getRawOriginal('amount'));
    }
}
