<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Address\Pages;

use App\MoonShine\Resources\Address\AddressResource;
use Domain\Profile\Models\Address;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends FormPage<AddressResource, Address>
 */
final class AddressFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),

                Text::make(__('moonshine.address.fields.label'), 'label'),
                Text::make(__('moonshine.address.fields.city'), 'city')->required(),
                Text::make(__('moonshine.address.fields.address'), 'address')->required(),
                Textarea::make(__('moonshine.address.fields.comment'), 'comment'),

                Switcher::make(__('moonshine.address.fields.is_default'), 'is_default')
                    ->hint('Address::booted() снимает этот флаг у остальных адресов пользователя при сохранении — по умолчанию может быть только один адрес.'),
            ]),
        ];
    }

    // Rules mirror App\Http\Controllers\Account\AddressController::validated()
    // — same fields, same constraints, both entry points into the same table.
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
