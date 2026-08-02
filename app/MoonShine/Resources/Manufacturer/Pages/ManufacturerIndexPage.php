<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Manufacturer\Pages;

use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ManufacturerResource>
 */
final class ManufacturerIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Название', 'name')->sortable(),
            Text::make('Slug', 'slug'),
            Switcher::make('Активен', 'is_active'),
            Number::make('Товаров', 'products_count')->badge(Color::GRAY),
        ];
    }
}
