<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Property\Pages;

use App\MoonShine\Resources\Property\PropertyResource;
use Domain\Catalog\Models\Property;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<PropertyResource, Property>
 */
final class PropertyFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),

                Text::make('Код', 'code')->required(),
                Text::make('Название', 'name')->required(),

                Select::make('Тип данных', 'data_type')
                    ->options(['string' => 'string', 'integer' => 'integer', 'decimal' => 'decimal', 'boolean' => 'boolean', 'reference' => 'reference'])
                    ->required()
                    ->hint('Соглашение по UI, БД хранит varchar(32) без ограничения набора значений.'),

                Text::make('Ед. изм.', 'unit')->nullable(),

                Text::make('Таблица хранения', 'storage_table')
                    ->required()
                    ->hint('Куда реально пишутся значения (products, beer_product_details) — метаданные, не FK.'),

                Text::make('Колонка хранения', 'storage_column')->required(),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('properties', 'code')->ignore($item->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'data_type' => ['required', 'string', 'max:32'],
            'unit' => ['nullable', 'string', 'max:32'],
            'storage_table' => ['required', 'string', 'max:64'],
            'storage_column' => [
                'required', 'string', 'max:64',
                Rule::unique('properties', 'storage_column')
                    ->where(fn ($query) => $query->where('storage_table', request('storage_table')))
                    ->ignore($item->getKey()),
            ],
        ];
    }
}
