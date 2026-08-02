<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Category\Pages;

use App\MoonShine\Resources\Category\CategoryResource;
use Domain\Catalog\Models\Category;
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
 * @extends FormPage<CategoryResource, Category>
 */
final class CategoryFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),

                Text::make(__('moonshine.category.fields.code'), 'code')
                    ->required()
                    ->hint('Перезаписывается каждым запуском CategorySeeder — правки здесь живут только до следующего сида.'),

                Text::make(__('moonshine.category.fields.name'), 'name')
                    ->required()
                    ->hint('Перезаписывается каждым запуском CategorySeeder — правки здесь живут только до следующего сида.'),

                Text::make(__('moonshine.category.fields.slug'), 'slug')
                    ->hint('Импорт-ключ (config/catalog_import.php) — по нему CategorySeeder ищет категорию. Генерируется из name только при пустом значении, повторно не перезаписывается.'),

                Switcher::make(__('moonshine.category.fields.is_active'), 'is_active'),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('categories', 'code')->ignore($item->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                ...$item->getKey() !== null ? ['required'] : ['nullable'],
                'string', 'max:255',
                Rule::unique('categories', 'slug')->ignore($item->getKey()),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
