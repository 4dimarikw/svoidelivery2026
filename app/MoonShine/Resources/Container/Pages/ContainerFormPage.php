<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Container\Pages;

use App\MoonShine\Resources\Container\ContainerResource;
use Domain\Catalog\Models\Container;
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
 * @extends FormPage<ContainerResource, Container>
 */
final class ContainerFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),

                Text::make('Код', 'code')
                    ->required()
                    ->hint('Ключ поиска для импорта (catalog_import.container_map). Переименование кода приведёт к предупреждениям "container code not seeded" и container_id = null у новых товаров на следующем импорте.'),

                Text::make('Название', 'name')->required(),

                Switcher::make('Активна', 'is_active'),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('containers', 'code')->ignore($item->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
