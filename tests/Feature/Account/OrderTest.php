<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use Domain\Order\Models\DeliveryType;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderItem;
use Domain\Order\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(User $user): Order
    {
        $order = Order::query()->create([
            'user_id' => $user->id,
            'delivery_type_id' => DeliveryType::query()->first()->id,
            'payment_method_id' => PaymentMethod::query()->first()->id,
            'amount' => 0,
        ]);

        $product = Product::factory()->create(['price' => 1000]);

        // OrderItemObserver (см. Domain\Order\Providers\OrderServiceProvider)
        // пересчитывает order.amount на этом create() — не проставляем вручную.
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'price' => $product->price,
            'quantity' => 2,
        ]);

        return $order->fresh();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('account.orders.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_users_orders(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = $this->createOrder($user);
        $this->createOrder($other);

        $response = $this->actingAs($user)->get(route('account.orders.index'));

        $response->assertOk();
        $response->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$mine->id]);
    }

    public function test_order_item_observer_recalculates_order_amount(): void
    {
        $order = $this->createOrder(User::factory()->create());

        $this->assertSame('2000.00', number_format($order->amount->major(), 2, '.', ''));
    }

    public function test_show_displays_order_for_its_owner(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user);

        $response = $this->actingAs($user)->get(route('account.orders.show', $order));

        $response->assertOk();
        $response->assertSee($order->number);
    }

    public function test_show_returns_403_for_another_users_order(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->createOrder($other);

        $this->actingAs($user)->get(route('account.orders.show', $order))->assertForbidden();
    }
}
