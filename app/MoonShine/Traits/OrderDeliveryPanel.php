<?php

declare(strict_types=1);

namespace App\MoonShine\Traits;

use Domain\Order\Actions\CreateOrderDelivery;
use Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\HttpMethod;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Enums\HtmlMode;

trait OrderDeliveryPanel
{
    protected function deliveryPanel(): array
    {
        $order = $this->getResource()->getItem();
        if (! $order instanceof Order || ! $order->exists) {
            return [];
        }

        return [Box::make('Доставка XML на FTP', [FlexibleRender::make($this->deliveryHtml($order))])];
    }

    private function deliveryHtml(Order $order): string
    {
        $resource = $this->getResource();
        $canSend = $resource->can(Ability::UPDATE);
        $button = fn (string $label, string $method, array $params) => ActionButton::make($label)
            ->method($method, params: ['resourceItem' => $order->id, ...$params], page: $this, resource: $resource)
            ->async(HttpMethod::POST)->setAttribute('data-async-method', HttpMethod::POST->value);
        $deliveries = $order->deliveries()->where('channel', 'ftp')->orderByDesc('version')->get();
        $retryButtons = [];
        if ($canSend) {
            foreach ($deliveries as $delivery) {
                if (in_array($delivery->state, ['sent', 'failed'], true)) {
                    $retryButtons[$delivery->id] = $button('Повторить отправку', 'retryDelivery', ['delivery_id' => $delivery->id]);
                }
            }
        }

        return view('moonshine.orders.deliveries', [
            'deliveries' => $deliveries, 'retryButtons' => $retryButtons,
            'newButton' => $canSend ? $button('Отправить новый XML', 'createDelivery', ['operation_key' => (string) Str::uuid()]) : null,
        ])->render();
    }

    private function deliveryOrder(CrudRequestContract $request): Order|JsonResponse
    {
        if (! $request->isMethod('post')) {
            return JsonResponse::make()->toast('Требуется POST-запрос', ToastType::ERROR)->setStatusCode(405);
        }
        $resource = $request->getResource();
        if (! $resource->can(Ability::UPDATE)) {
            return JsonResponse::make()->toast('Нет права изменения заказа', ToastType::ERROR)->setStatusCode(403);
        }
        $order = $resource->getItem();
        if (! $order instanceof Order || ! $order->exists) {
            return JsonResponse::make()->toast('Заказ не найден', ToastType::ERROR)->setStatusCode(404);
        }

        return $order;
    }

    #[AsyncMethod]
    public function createDelivery(CrudRequestContract $request): JsonResponse
    {
        $order = $this->deliveryOrder($request);
        if ($order instanceof JsonResponse) {
            return $order;
        }
        $data = validator(['operation_key' => $request->get('operation_key')], ['operation_key' => ['required', 'uuid']])->validate();
        app(CreateOrderDelivery::class)->newVersion($order->id, $data['operation_key']);

        return JsonResponse::make()->toast('Поставлено на отправку', ToastType::SUCCESS)
            ->html(['#order-deliveries' => $this->deliveryHtml($order)], HtmlMode::OUTER_HTML);
    }

    #[AsyncMethod]
    public function retryDelivery(CrudRequestContract $request): JsonResponse
    {
        $order = $this->deliveryOrder($request);
        if ($order instanceof JsonResponse) {
            return $order;
        }
        $data = validator(['delivery_id' => $request->get('delivery_id')], ['delivery_id' => ['required', 'integer', 'min:1']])->validate();
        try {
            $queued = app(CreateOrderDelivery::class)->retry($order->id, (int) $data['delivery_id']);
        } catch (ModelNotFoundException) {
            return JsonResponse::make()->toast('Доставка заказа не найдена', ToastType::ERROR)->setStatusCode(404);
        }

        return JsonResponse::make()->toast($queued ? 'Поставлено на отправку' : 'Доставка уже ожидает отправки или выполняется', ToastType::INFO)
            ->html(['#order-deliveries' => $this->deliveryHtml($order)], HtmlMode::OUTER_HTML);
    }
}
