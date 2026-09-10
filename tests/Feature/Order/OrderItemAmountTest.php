<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\MoonShine\Resources\OrderItem\Pages\OrderItemFormPage;
use Domain\Catalog\Actions\UpdateAmountOrder;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class OrderItemAmountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Storage::fake('ftp');
    }

    public function test_price_only_edit_recalculates_amount(): void
    {
        $item = OrderItem::factory()->create(['price' => '12.34', 'quantity' => 3]);
        $item->update(['price' => '45.67']);
        $this->assertSame('137.01', $item->order->fresh()->getRawOriginal('amount'));
    }

    public function test_transfer_updates_both_orders_and_ignores_warm_relations(): void
    {
        $item = OrderItem::factory()->create(['price' => '12.34', 'quantity' => 3]);
        $source = $item->order->load('orderItems');
        $other = OrderItem::factory()->create(['price' => '5.00', 'quantity' => 2]);
        $target = $other->order->load('orderItems');
        $item->update(['order_id' => $target->id, 'price' => '20.00']);
        $this->assertSame('0.00', $source->fresh()->getRawOriginal('amount'));
        $this->assertSame('70.00', $target->fresh()->getRawOriginal('amount'));
        app(UpdateAmountOrder::class)->execute($source);
        app(UpdateAmountOrder::class)->execute($target);
        $this->assertSame('0.00', $source->getRawOriginal('amount'));
        $this->assertSame('70.00', $target->getRawOriginal('amount'));
    }

    public function test_create_and_delete_recalculate_with_preloaded_order(): void
    {
        $order = Order::factory()->create(['amount' => 0]);
        $order->load('orderItems');
        $item = OrderItem::factory()->make(['order_id' => $order->id, 'price' => '10.01', 'quantity' => 2]);
        $item->setRelation('order', $order)->save();
        $this->assertSame('20.02', $order->fresh()->getRawOriginal('amount'));
        $order->load('orderItems');
        $item->delete();
        $this->assertSame('0.00', $order->fresh()->getRawOriginal('amount'));
    }

    public function test_failure_during_second_total_rolls_back_item_and_both_totals(): void
    {
        $item = OrderItem::factory()->create(['price' => '10.00', 'quantity' => 2]);
        $source = $item->order;
        $target = Order::factory()->create(['amount' => 0]);
        $before = $item->fresh()->getAttributes();
        Event::listen('eloquent.saving: '.Order::class, function (Order $order) use ($target): void {
            if ($order->id === $target->id && $order->isDirty('amount')) {
                throw new RuntimeException('Simulated total failure');
            }
        });
        try {
            $item->update(['order_id' => $target->id, 'price' => '15.00']);
            $this->fail('Expected failure');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated total failure', $e->getMessage());
        }
        $this->assertSame($before, $item->fresh()->getAttributes());
        $this->assertSame('20.00', $source->fresh()->getRawOriginal('amount'));
        $this->assertSame('0.00', $target->fresh()->getRawOriginal('amount'));
    }

    public function test_stale_item_cannot_overwrite_a_transfer(): void
    {
        $item = OrderItem::factory()->create();
        $stale = $item->fresh();
        $target = Order::factory()->create();
        $item->update(['order_id' => $target->id]);
        $this->expectException(ValidationException::class);
        $stale->update(['price' => '1.00']);
    }

    public function test_admin_rules_reject_transfer_but_allow_price_edit_and_new_item(): void
    {
        $item = OrderItem::factory()->create();
        $target = Order::factory()->create();
        $page = app(OrderItemFormPage::class);
        $method = new ReflectionMethod($page, 'rules');
        $wrapper = $this->createMock(DataWrapperContract::class);
        $wrapper->method('getOriginal')->willReturn($item);
        $rules = $method->invoke($page, $wrapper);
        $payload = ['price' => '20.00', 'quantity' => 2, 'product_id' => $item->product_id, 'order_id' => $target->id];
        $validator = Validator::make($payload, $rules);
        $this->assertTrue($validator->fails());
        $this->assertSame([__('order.errors.item_transfer_forbidden')], $validator->errors()->get('order_id'));
        $payload['order_id'] = $item->order_id;
        $this->assertFalse(Validator::make($payload, $rules)->fails());
        $newWrapper = $this->createMock(DataWrapperContract::class);
        $newWrapper->method('getOriginal')->willReturn(new OrderItem);
        $payload['order_id'] = $target->id;
        $this->assertFalse(Validator::make($payload, $method->invoke($page, $newWrapper))->fails());
    }
}
