<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Property\Pages;

use App\MoonShine\Resources\Property\PropertyResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<PropertyResource>
 */
final class PropertyIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make(__('moonshine.property.fields.code'), 'code')->sortable(),
            Text::make(__('moonshine.property.fields.name'), 'name')->sortable(),
            Text::make(__('moonshine.property.index.type'), 'data_type'),
            Text::make(__('moonshine.property.fields.unit'), 'unit'),
            Text::make(__('moonshine.property.index.storage_table'), 'storage_table'),
            Text::make(__('moonshine.property.index.storage_column'), 'storage_column'),
        ];
    }
}
