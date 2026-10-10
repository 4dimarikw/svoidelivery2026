<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Seo\Pages;

use App\MoonShine\Resources\Seo\SeoFields;
use App\MoonShine\Resources\Seo\SeoResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\DetailPage;

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
        return SeoFields::detail();
    }
}
