<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use Domain\Order\Actions\CreateOrderDelivery;
use Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class OrderDeliveryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_and_detail_render_versions_and_post_buttons_without_xml_contents(): void
    {
        $admin = MoonshineUser::create(['email' => 'delivery@example.test', 'name' => 'Delivery test',
            'password' => bcrypt('password'), 'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID]);
        $this->actingAs($admin, 'moonshine');
        $order = Order::create([]);
        $delivery = app(CreateOrderDelivery::class)->initial($order->id);
        $delivery->update(['state' => 'sent', 'attempts' => 1, 'cycle_attempts' => 1, 'sent_at' => now()]);
        foreach (['order-form-page', 'order-detail-page'] as $page) {
            $response = $this->get('/admin/resource/order-resource/'.$page.'/'.$order->id)->assertOk();
            $response->assertSee('Отправить новый XML')->assertSee('Повторить отправку')
                ->assertSee('Загружено на FTP')->assertSee('operation_key')->assertDontSee('<Orders>', false);
            $this->assertStringContainsString('data-async-method="post"', strtolower($response->getContent()));
        }
    }

    public function test_admin_post_is_idempotent_and_retry_is_scoped_to_its_order(): void
    {
        $admin = MoonshineUser::create(['email' => 'delivery-post@example.test', 'name' => 'Delivery test',
            'password' => bcrypt('password'), 'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID]);
        $this->actingAs($admin, 'moonshine');
        $order = Order::create([]);
        $html = $this->get('/admin/resource/order-resource/order-form-page/'.$order->id)->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $links = (new \DOMXPath($document))->query('//div[@id="order-deliveries"]//a');
        $url = $links->item(0)->getAttribute('href');
        $this->getJson($url)->assertStatus(405);
        $this->assertSame(0, $order->deliveries()->count());
        $this->postJson($url)->assertOk()->assertJsonPath('message', 'Поставлено на отправку');
        $this->postJson($url)->assertOk();
        $delivery = $order->deliveries()->sole();
        $delivery->update(['state' => 'sent']);

        $retryUrl = '/admin/method/order-form-page/order-resource?'.http_build_query([
            'method' => 'retryDelivery', 'resourceItem' => $order->id, 'delivery_id' => $delivery->id,
        ]);
        $this->postJson($retryUrl)->assertOk();
        $this->assertSame('pending', $delivery->fresh()->state);
        $this->postJson($retryUrl)->assertOk()->assertJsonPath('message', 'Доставка уже ожидает отправки или выполняется');
        $another = Order::create([]);
        $foreignUrl = '/admin/method/order-form-page/order-resource?'.http_build_query([
            'method' => 'retryDelivery', 'resourceItem' => $another->id, 'delivery_id' => $delivery->id,
        ]);
        $this->postJson($foreignUrl)->assertNotFound();
    }
}
