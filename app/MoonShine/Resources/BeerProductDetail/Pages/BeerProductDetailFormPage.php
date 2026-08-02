<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BeerProductDetail\Pages;

use App\MoonShine\Resources\BeerProductDetail\BeerProductDetailResource;
use App\MoonShine\Resources\BeerStyle\BeerStyleResource;
use Domain\Catalog\Models\BeerProductDetail;
use Domain\Catalog\Models\BeerStyle;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;

/**
 * Схема полей, которую RelationRepeater::make(..., 'beerDetails') в
 * ProductFormPage переопределяет собственным ->fields(...) — этот класс лишь
 * даёт ModelRelationField валидный ModelResource для beerDetails (см.
 * BeerProductDetailResource).
 *
 * @extends FormPage<BeerProductDetailResource, BeerProductDetail>
 */
final class BeerProductDetailFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make('ID', 'product_id'),

            BelongsTo::make(
                __('moonshine.product.fields.beer_style'),
                'beerStyle',
                formatted: static fn (BeerStyle $model) => $model->name,
                resource: BeerStyleResource::class,
            )->nullable(),

            Number::make(__('moonshine.product.fields.abv'), 'abv')->step(0.01),
            Number::make(__('moonshine.product.fields.ibu'), 'ibu')->step(0.01),
            Number::make(__('moonshine.product.fields.plato'), 'plato')->step(0.01),
            Number::make(__('moonshine.product.fields.ebc'), 'ebc')->step(0.01),
        ];
    }
}
