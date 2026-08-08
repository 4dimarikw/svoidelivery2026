<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\DeliveryType\Pages;

use App\MoonShine\Resources\DeliveryType\DeliveryTypeResource;
use Domain\Order\Models\DeliveryType;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends DetailPage<DeliveryTypeResource>
 */
class DeliveryTypeDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make('id'),
            Text::make(__('moonshine.delivery_type.fields.title'), 'title'),
            Number::make(__('moonshine.delivery_type.fields.price'), 'price')
                ->changeFill(static fn (DeliveryType $deliveryType) => $deliveryType->price?->major()),
            Switcher::make(__('moonshine.delivery_type.fields.with_address'), 'with_address'),
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
        return $component;
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
