<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Favorite;

use App\MoonShine\Resources\Favorite\Pages\FavoriteFormPage;
use App\MoonShine\Resources\Favorite\Pages\FavoriteIndexPage;
use Domain\Favorite\Models\Favorite;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * Полноценный CRUD-раздел (в отличие от AddressResource, который существует
 * только как цель HasMany в UserFormPage) — избранное можно администрировать
 * напрямую, не только через карточку пользователя.
 *
 * @extends ModelResource<Favorite, FavoriteIndexPage, FavoriteFormPage, null>
 */
#[Icon('heart')]
#[Group('moonshine.group.users', 'users', translatable: true)]
#[Order(2)]
class FavoriteResource extends ModelResource
{
    protected string $model = Favorite::class;

    protected bool $withPolicy = true;

    protected string $column = 'id';

    protected array $with = ['user', 'product'];

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    public function getTitle(): string
    {
        return __('moonshine.favorite.title');
    }

    protected function pages(): array
    {
        return [
            FavoriteIndexPage::class,
            FavoriteFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id'];
    }
}
