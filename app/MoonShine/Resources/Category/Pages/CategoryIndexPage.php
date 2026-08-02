<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Category\Pages;

use App\MoonShine\Resources\Category\CategoryResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<CategoryResource>
 */
final class CategoryIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make(__('moonshine.category.fields.code'), 'code')->sortable(),
            Text::make(__('moonshine.category.fields.name'), 'name')->sortable(),
            Text::make(__('moonshine.category.fields.slug'), 'slug'),
            Switcher::make(__('moonshine.category.fields.is_active'), 'is_active'),
            Switcher::make(__('moonshine.category.fields.expects_container'), 'expects_container'),
            Switcher::make(__('moonshine.category.fields.expects_volume'), 'expects_volume'),
            Number::make(__('moonshine.category.fields.match_rules_count'), 'match_rules_count')->badge(Color::PURPLE),
            Number::make(__('moonshine.category.fields.products_count'), 'products_count')->badge(Color::GRAY),
        ];
    }
}
