<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Volume\Pages;

use App\MoonShine\Resources\Volume\VolumeResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<VolumeResource>
 */
final class VolumeIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Number::make('Объём, мл', 'milliliters')->sortable(),
            Text::make('Название', 'label')->sortable(),
            Number::make('Товаров', 'products_count')->badge(Color::GRAY),
        ];
    }
}
