<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Manufacturer\Pages;

use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use Domain\Catalog\Models\Manufacturer;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<ManufacturerResource, Manufacturer>
 */
final class ManufacturerFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),

                Text::make(__('moonshine.manufacturer.fields.name'), 'name')
                    ->required()
                    ->hint('Переименование безопасно для сайта, но следующий импорт всё ещё ищет производителя по исходному имени — переименованный бренд будет создан заново, а не найден.'),

                Text::make(__('moonshine.manufacturer.fields.slug'), 'slug')
                    ->hint('Заполняется автоматически при создании, если оставить пустым. При переименовании не пересчитывается.'),

                Switcher::make(__('moonshine.manufacturer.fields.is_active'), 'is_active'),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // 'required' only on update — on create, a blank slug is filled
            // in by Manufacturer::booted()'s creating hook.
            'slug' => [
                ...$item->getKey() !== null ? ['required'] : ['nullable'],
                'string', 'max:255',
                Rule::unique('manufacturers', 'slug')->ignore($item->getKey()),
            ],
        ];
    }
}
