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
            Text::make(__('moonshine.container.fields.code'), 'code')->sortable(),
            Text::make(__('moonshine.container.fields.name'), 'name')->sortable(),
            Text::make(__('moonshine.container.fields.label'), 'label')->sortable(),
            Switcher::make(__('moonshine.container.fields.is_active'), 'is_active'),
            Number::make(__('moonshine.container.fields.products_count'), 'products_count')->badge(Color::GRAY),
        ];
    }
}
