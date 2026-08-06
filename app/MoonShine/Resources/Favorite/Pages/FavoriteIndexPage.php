<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Favorite\Pages;

use App\MoonShine\Resources\Favorite\FavoriteResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\User\UserResource;
use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;

/**
 * @extends IndexPage<FavoriteResource>
 */
final class FavoriteIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make(
                __('moonshine.favorite.fields.user'),
                'user',
                formatted: static fn (User $model) => $model->name,
                resource: UserResource::class,
            ),

            BelongsTo::make(
                __('moonshine.favorite.fields.product'),
                'product',
                formatted: static fn (Product $model) => $model->name,
                resource: ProductResource::class,
            ),

            Date::make(__('moonshine.favorite.fields.created_at'), 'created_at')
                ->withTime()
                ->sortable(),
        ];
    }
}
