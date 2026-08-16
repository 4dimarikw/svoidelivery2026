<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SiteMenu\Pages;

use App\MoonShine\Resources\SiteMenu\SiteMenuResource;
use App\MoonShine\Traits\ChecksSuperUser;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/** @extends IndexPage<SiteMenuResource> */
final class SiteMenuIndexPage extends IndexPage
{
    use ChecksSuperUser;

    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Название', 'title'),
            Text::make('Ключ', 'key')->canSee(fn () => $this->isSuperUser()),
            Switcher::make('Активно', 'is_active'),
        ];
    }

    protected function filters(): iterable
    {
        return [Switcher::make('Активно', 'is_active')];
    }
}
