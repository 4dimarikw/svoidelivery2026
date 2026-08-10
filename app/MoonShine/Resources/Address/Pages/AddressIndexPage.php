<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Address\Pages;

use App\MoonShine\Resources\Address\AddressResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<AddressResource>
 */
final class AddressIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make(__('moonshine.address.fields.label'), 'label'),
            Text::make(__('moonshine.address.fields.city'), 'city'),
            Text::make(__('moonshine.address.fields.address'), 'address'),
            Switcher::make(__('moonshine.address.fields.is_default'), 'is_default'),
        ];
    }
}
