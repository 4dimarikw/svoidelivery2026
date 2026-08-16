<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SiteMenu;

use App\MoonShine\Resources\SiteMenu\Pages\SiteMenuFormPage;
use App\MoonShine\Resources\SiteMenu\Pages\SiteMenuIndexPage;
use Domain\Content\Models\SiteMenu;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/** @extends ModelResource<SiteMenu, SiteMenuIndexPage, SiteMenuFormPage, null> */
#[Icon('bars-3')]
#[Group('Контент', 'document-text')]
#[Order(20)]
final class SiteMenuResource extends ModelResource
{
    protected string $model = SiteMenu::class;

    protected string $column = 'title';

    protected array $with = ['items'];

    public function getTitle(): string
    {
        return 'Меню';
    }

    protected function pages(): array
    {
        return [SiteMenuIndexPage::class, SiteMenuFormPage::class];
    }

    protected function search(): array
    {
        return ['id', 'key', 'title'];
    }
}
