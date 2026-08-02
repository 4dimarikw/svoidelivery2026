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
            Text::make('Код', 'code')->sortable(),
            Text::make('Название', 'name')->sortable(),
            Text::make('Тип', 'data_type'),
            Text::make('Ед. изм.', 'unit'),
            Text::make('Таблица', 'storage_table'),
            Text::make('Колонка', 'storage_column'),
        ];
    }
}
