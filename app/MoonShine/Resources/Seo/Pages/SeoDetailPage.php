<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Seo\Pages;

use App\MoonShine\Resources\Seo\SeoResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends DetailPage<SeoResource>
 */
class SeoDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Url'),
            Text::make('Title'),
            Text::make('Description'),
            Text::make('Keywords'),
            Text::make('Text'),
        ];
    }
}
