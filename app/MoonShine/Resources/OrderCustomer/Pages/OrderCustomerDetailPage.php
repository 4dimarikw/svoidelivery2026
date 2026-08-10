<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\OrderCustomer\Pages;

use App\MoonShine\Resources\OrderCustomer\OrderCustomerResource;
use App\MoonShine\Traits\CustomUI;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends DetailPage<OrderCustomerResource>
 */
class OrderCustomerDetailPage extends DetailPage
{
    use CustomUI;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make('id'),

            Text::make(__('moonshine.order_customer.fields.first_name'), 'first_name'),

            Text::make(__('moonshine.order_customer.fields.last_name'), 'last_name'),

            Text::make(__('moonshine.order_customer.fields.phone'), 'phone'),

            Text::make(__('moonshine.order_customer.fields.city'), 'city'),

            Text::make(__('moonshine.order_customer.fields.address'), 'address'),

            Text::make(__('moonshine.order_customer.fields.comment'), 'comment'),
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
