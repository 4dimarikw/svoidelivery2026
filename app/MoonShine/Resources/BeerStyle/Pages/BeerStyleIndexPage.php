<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BeerStyle\Pages;

use App\MoonShine\Resources\BeerStyle\BeerStyleResource;
use Domain\Catalog\Models\BeerStyle;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<BeerStyleResource>
 */
final class BeerStyleIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Название', 'name')->sortable(),
            BelongsTo::make(
                'Родительский стиль',
                'parent',
                formatted: static fn (BeerStyle $model) => $model->name,
                resource: BeerStyleResource::class,
            ),
            Text::make('Slug', 'slug'),
            Switcher::make('Активен', 'is_active'),
            Number::make('Товаров', 'beer_product_details_count')->badge(Color::GRAY),
        ];
    }
}
