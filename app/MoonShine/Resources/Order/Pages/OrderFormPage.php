<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Order\Pages;

use App\MoonShine\Resources\DeliveryType\DeliveryTypeResource;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\OrderCustomer\OrderCustomerResource;
use App\MoonShine\Resources\OrderItem\OrderItemResource;
use App\MoonShine\Resources\PaymentMethod\PaymentMethodResource;
use App\MoonShine\Resources\User\UserResource;
use App\MoonShine\Traits\OrderDeliveryPanel;
use Domain\Order\Enums\OrderStatuses;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Fields\Relationships\HasOne;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<OrderResource>
 */
class OrderFormPage extends FormPage
{
    use OrderDeliveryPanel;

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Grid::make([
                Column::make([
                    Box::make([
                        ID::make('id'),
                        Number::make('id', formatted: fn ($item) => 'ID: '.$item->id)->previewMode(),

                    ]),
                ])->canSee(fn () => request()->user('moonshine')->isSuperUser()),
                Column::make([
                    Box::make('Данные заказа', [
                        Text::make(__('moonshine.order.fields.number'), 'number'),
                        Text::make(__('moonshine.order.fields.comment'), 'comment'),

                    ]),
                    Box::make([
                        Text::make(__('moonshine.order.fields.amount'), 'amount')->locked()->hint('При изменении состава заказа обновите страницу!'),

                    ]),
                    HasOne::make(__('moonshine.order_customer.title'), 'orderCustomer', resource: OrderCustomerResource::class)
                        ->nullable()
                        ->tabMode(),
                    HasMany::make(__('moonshine.order_item.title'), 'orderItems', resource: OrderItemResource::class)
                        ->creatable()
                        ->tabMode(),
                ], 8, 8),
                Column::make([
                    Box::make('Параметры заказа', [
                        Enum::make(__('moonshine.order.fields.status'), 'status')
                            ->attach(OrderStatuses::class),
                        BelongsTo::make(__('moonshine.user.title'), 'user', resource: UserResource::class),
                        BelongsTo::make(__('moonshine.delivery_type.title'), 'deliveryType', resource: DeliveryTypeResource::class),
                        BelongsTo::make(__('moonshine.payment_method.title'), 'paymentMethod', resource: PaymentMethodResource::class)
                            ->canSee(fn () => request()->user('moonshine')->isSuperUser()),
                    ]),
                ], 4, 4),
            ]),
        ];
    }

    protected function bottomLayer(): array
    {
        return [...parent::bottomLayer(), ...$this->deliveryPanel()];
    }
}
