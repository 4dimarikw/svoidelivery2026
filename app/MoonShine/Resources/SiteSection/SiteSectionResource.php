<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SiteSection;

use App\MoonShine\Resources\SiteSection\Pages\SiteSectionFormPage;
use App\MoonShine\Resources\SiteSection\Pages\SiteSectionIndexPage;
use Domain\Content\Models\SiteSection;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\SortDirection;

/** @extends ModelResource<SiteSection, SiteSectionIndexPage, SiteSectionFormPage, null> */
#[Icon('document-text')]
#[Group('Контент', 'document-text')]
#[Order(10)]
final class SiteSectionResource extends ModelResource
{
    protected string $model = SiteSection::class;

    protected bool $withPolicy = true;

    protected string $column = 'title';

    protected array $with = ['blocks'];

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    public function getTitle(): string
    {
        return 'Разделы сайта';
    }

    protected function pages(): array
    {
        return [SiteSectionIndexPage::class, SiteSectionFormPage::class];
    }

    protected function search(): array
    {
        return ['id', 'key', 'title', 'route_name'];
    }
}
