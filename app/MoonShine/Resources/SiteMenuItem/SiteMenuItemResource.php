<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SiteMenuItem;


use App\MoonShine\Resources\SiteMenuItem\Pages\SiteMenuItemFormPage;
use App\MoonShine\Resources\SiteMenuItem\Pages\SiteMenuItemIndexPage;
use Domain\Content\Models\SiteMenuItem;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\SortDirection;

/** @extends ModelResource<SiteMenuItem, SiteMenuItemIndexPage, SiteMenuItemFormPage, null> */
#[Icon('bars-3-bottom-left')]
#[Group('Контент', 'document-text')]
#[Order(30)]
final class SiteMenuItemResource extends ModelResource
{
    protected string $model = SiteMenuItem::class;

    protected string $column = 'label';

    protected array $with = ['menu', 'section', 'parent'];

    protected string $sortColumn = '_lft';

    protected SortDirection $sortDirection = SortDirection::ASC;

    public function getTitle(): string
    {
        return 'Пункты меню';
    }

    protected function pages(): array
    {
        return [SiteMenuItemIndexPage::class, SiteMenuItemFormPage::class];
    }

    protected function search(): array
    {
        return ['id', 'key', 'label', 'external_url'];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->orderBy('site_menu_id');
    }

    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        return DB::transaction(fn(): DataWrapperContract => parent::save($item, $fields), 3);
    }

    public function delete(DataWrapperContract $item, ?FieldsContract $fields = null): bool
    {
        return DB::transaction(fn(): bool => parent::delete($item, $fields), 3);
    }

    public function massDelete(array $ids): void
    {
        throw ValidationException::withMessages([
            'ids' => 'Пункты вложенного меню удаляются только по одному.',
        ]);
    }
}
