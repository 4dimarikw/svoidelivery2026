<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Order\Pages;

use App\MoonShine\Resources\DeliveryType\DeliveryTypeResource;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\OrderCustomer\OrderCustomerResource;
use App\MoonShine\Resources\OrderItem\OrderItemResource;
use App\MoonShine\Resources\PaymentMethod\PaymentMethodResource;
use App\MoonShine\Resources\User\UserResource;
use App\MoonShine\Traits\CustomUI;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Fields\Relationships\HasOne;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends DetailPage<OrderResource>
 */
class OrderDetailPage extends DetailPage
{
    use CustomUI;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make('id'),

            Text::make(__('moonshine.order.fields.number'), 'number'),

            Text::make(__('moonshine.order.fields.comment'), 'comment'),

            Text::make(__('moonshine.order.fields.amount'), 'amount'),

            Text::make(__('moonshine.order.fields.status'), 'status'),

            BelongsTo::make(__('moonshine.user.title'), 'user', resource: UserResource::class),

            BelongsTo::make(__('moonshine.delivery_type.title'), 'deliveryType', resource: DeliveryTypeResource::class),

            BelongsTo::make(__('moonshine.payment_method.title'), 'paymentMethod', resource: PaymentMethodResource::class),

            HasOne::make(__('moonshine.order_customer.title'), 'orderCustomer', resource: OrderCustomerResource::class)
                ->tabMode()
                ->modifyTable(fn (TableBuilder $table) => $this->modifyTableVertical($table)),

            HasMany::make(__('moonshine.order_item.title'), 'orderItems', resource: OrderItemResource::class)
                ->tabMode(),
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    /**
     * @param  TableBuilder  $component
     * @return TableBuilder
     */
    protected function modifyDetailComponent(ComponentContract $component): ComponentContract
    {
        return $this->modifyTableVertical($component);
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer(),
        ];
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            ...parent::mainLayer(),
        ];
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer(),
        ];
    }
}
