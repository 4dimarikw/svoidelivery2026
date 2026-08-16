<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ContentBlockItem;

use App\MoonShine\Resources\ContentBlockItem\Pages\ContentBlockItemFormPage;
use App\MoonShine\Resources\ContentBlockItem\Pages\ContentBlockItemIndexPage;
use Domain\Content\Models\ContentBlockItem;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\SortDirection;

/** @extends ModelResource<ContentBlockItem, ContentBlockItemIndexPage, ContentBlockItemFormPage, null> */
#[Icon('list-bullet')]
#[Group('Контент', 'document-text')]
#[Order(50)]
final class ContentBlockItemResource extends ModelResource
{
    protected string $model = ContentBlockItem::class;

    protected bool $withPolicy = true;

    protected string $column = 'title';

    protected array $with = ['block.section'];

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    public function getTitle(): string
    {
        return 'Элементы блоков';
    }

    protected function pages(): array
    {
        return [ContentBlockItemIndexPage::class, ContentBlockItemFormPage::class];
    }

    protected function search(): array
    {
        return ['id', 'key', 'title', 'group_key', 'content'];
    }
}
