<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BeerStyle\Pages;

use App\MoonShine\Resources\BeerStyle\BeerStyleResource;
use Domain\Catalog\Models\BeerStyle;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends FormPage<BeerStyleResource, BeerStyle>
 */
final class BeerStyleFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        $currentId = $this->getItem()?->getKey();

        return [
            Box::make([
                ID::make(),

                BelongsTo::make(
                    'Родительский стиль',
                    'parent',
                    formatted: static fn (BeerStyle $model) => $model->name,
                    resource: BeerStyleResource::class,
                )
                    ->nullable()
                    ->valuesQuery(static fn (Builder $q) => $q->when(
                        $currentId !== null,
                        static fn (Builder $q) => $q->whereKeyNot($currentId),
                    )),

                Text::make('Название', 'name')
                    ->required()
                    ->hint('Переименование безопасно для сайта, но следующий импорт всё ещё ищет стиль по исходному имени — переименованный стиль будет создан заново, а не найден.'),

                Text::make('Slug', 'slug')
                    ->hint('Заполняется автоматически при создании, если оставить пустым. При переименовании не пересчитывается.'),

                Switcher::make('Активен', 'is_active'),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:beer_styles,id'],
            'name' => ['required', 'string', 'max:255'],
            // 'required' only on update — on create, a blank slug is filled
            // in by BeerStyle::booted()'s creating hook.
            'slug' => [
                ...$item->getKey() !== null ? ['required'] : ['nullable'],
                'string', 'max:255',
                Rule::unique('beer_styles', 'slug')->ignore($item->getKey()),
            ],
        ];
    }
}
