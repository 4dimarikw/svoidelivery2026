<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\OrderCustomer\Pages;

use App\MoonShine\Resources\OrderCustomer\OrderCustomerResource;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<OrderCustomerResource>
 */
class OrderCustomerFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Grid::make([
                Column::make([
                    Box::make('Личные данные', [
                        Flex::make([
                            Text::make(__('moonshine.order_customer.fields.first_name'), 'first_name'),
                            Text::make(__('moonshine.order_customer.fields.last_name'), 'last_name'),
                        ]),
                        Text::make(__('moonshine.order_customer.fields.phone'), 'phone'),
                    ]),
                    Box::make('Адрес доставки', [
                        Flex::make([
                            Text::make(__('moonshine.order_customer.fields.city'), 'city')->nullable(),
                            Text::make(__('moonshine.order_customer.fields.address'), 'address')->nullable(),
                        ]),
                        Text::make(__('moonshine.order_customer.fields.comment'), 'comment')->nullable(),
                    ]),
                ], 8, 8),
                Column::make([
                    Box::make([
                        ID::make('id'),
                        Number::make('id', formatted: fn ($item) => 'ID: '.$item->id)->previewMode(),
                    ]),
                ], 4, 4),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        // with_address, не сравнение title === 'Самовывоз' — тот же bool-флаг,
        // что использует Processes\TermsOrder/AssignCustomer.
        $addressRequired = $item->getOriginal()->order->deliveryType->with_address;
        $addressRule = $addressRequired ? ['string', 'required'] : ['nullable', 'string'];

        return [
            'first_name' => ['string', 'required'],
            'last_name' => ['string', 'required'],
            'phone' => ['string', 'required'],
            'city' => $addressRule,
            'address' => $addressRule,
            'comment' => ['nullable', 'string'],
        ];
    }
}
