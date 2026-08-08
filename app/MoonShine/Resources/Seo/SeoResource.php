<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Seo;

use App\MoonShine\Resources\Seo\Pages\SeoDetailPage;
use App\MoonShine\Resources\Seo\Pages\SeoFormPage;
use App\MoonShine\Resources\Seo\Pages\SeoIndexPage;
use Leeto\Seo\Models\Seo;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * @extends ModelResource<Seo, SeoIndexPage, SeoFormPage, SeoDetailPage>
 */
#[Icon('star')]
#[Group('moonshine.group.catalog', 'squares-2x2', translatable: true)]
#[Order(10)]
class SeoResource extends ModelResource
{
    protected string $model = Seo::class;

    protected string $title = 'Seo';

    protected string $column = 'title';

    protected bool $detailInModal = true;

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            SeoIndexPage::class,
            SeoFormPage::class,
            SeoDetailPage::class,
        ];
    }

    public function search(): array
    {
        return ['id', 'url', 'title'];
    }
}
