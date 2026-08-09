<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SiteSection\Pages;

use App\MoonShine\Resources\SiteSection\SiteSectionResource;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/** @extends IndexPage<SiteSectionResource> */
final class SiteSectionIndexPage extends IndexPage
{
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Название', 'title')->sortable(),
            Text::make('Ключ', 'key'),
            Text::make('Маршрут', 'route_name'),
            Text::make('Якорь', 'fragment'),
            Number::make('Порядок', 'sort_order')->sortable(),
            Switcher::make('Активен', 'is_active'),
        ];
    }

    protected function filters(): iterable
    {
        return [Switcher::make('Активен', 'is_active')];
    }
}
