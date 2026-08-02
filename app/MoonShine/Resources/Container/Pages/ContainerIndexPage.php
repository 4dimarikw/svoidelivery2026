<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Container\Pages;

use App\MoonShine\Resources\Container\ContainerResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<ContainerResource>
 */
final class ContainerIndexPage extends IndexPage
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
            Switcher::make('Активна', 'is_active'),
            Number::make('Товаров', 'products_count')->badge(Color::GRAY),
        ];
    }
}
