<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\DeliveryType\Pages;

use App\MoonShine\Resources\DeliveryType\DeliveryTypeResource;
use Domain\Order\Models\DeliveryType;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends FormPage<DeliveryTypeResource>
 */
class DeliveryTypeFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make('id'),
            Text::make(__('moonshine.delivery_type.fields.title'), 'title'),
            Number::make(__('moonshine.delivery_type.fields.price'), 'price')
                ->required()
                ->step(0.01)
                // price закастован в Support\ValueObjects\Price — полю нужен
                // голый скаляр в рублях, не объект (см. ProductFormPage).
                ->changeFill(static fn (DeliveryType $deliveryType) => $deliveryType->price?->major()),
            Switcher::make(__('moonshine.delivery_type.fields.with_address'), 'with_address'),
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons();
    }

    protected function formButtons(): ListOf
    {
        return parent::formButtons();
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'title' => ['string', 'required'],
            'price' => ['required', 'numeric', 'min:0'],
            'with_address' => ['boolean'],
        ];
    }

    /**
     * @param  FormBuilder  $component
     * @return FormBuilder
     */
    protected function modifyFormComponent(FormBuilderContract $component): FormBuilderContract
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
