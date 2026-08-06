<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Favorite\Pages;

use App\MoonShine\Resources\Favorite\FavoriteResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\User\UserResource;
use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use Domain\Favorite\Models\Favorite;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;

/**
 * @extends FormPage<FavoriteResource, Favorite>
 */
final class FavoriteFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),

                BelongsTo::make(
                    __('moonshine.favorite.fields.user'),
                    'user',
                    formatted: static fn (User $model) => $model->name,
                    resource: UserResource::class,
                )
                    ->required()
                    ->asyncSearch('name'),

                BelongsTo::make(
                    __('moonshine.favorite.fields.product'),
                    'product',
                    formatted: static fn (Product $model) => $model->name,
                    resource: ProductResource::class,
                )
                    ->required()
                    ->asyncSearch('name'),
            ]),
        ];
    }

    // unique(favorites, [user_id, product_id]) — без scoped-правила форма
    // падала бы в общую 500-ю от нарушения индекса вместо понятной ошибки
    // валидации на конкретном поле.
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'user_id' => [
                'required',
                'exists:users,id',
                Rule::unique('favorites')
                    ->where('user_id', request()->input('user_id'))
                    ->where('product_id', request()->input('product_id'))
                    ->ignore($item->getKey()),
            ],
            'product_id' => ['required', 'exists:products,id'],
        ];
    }
}
