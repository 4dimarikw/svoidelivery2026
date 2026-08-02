<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BeerProductDetail\Pages;

use App\MoonShine\Resources\BeerProductDetail\BeerProductDetailResource;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;

/**
 * Не используется напрямую (ресурс #[SkipMenu]) — существует только затем,
 * чтобы RelationRepeater в ProductFormPage мог отрисовать fields() как
 * дефолтную схему при отсутствии явного ->fields(...).
 *
 * @extends IndexPage<BeerProductDetailResource>
 */
final class BeerProductDetailIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make('ID', 'product_id'),
            Number::make(__('moonshine.product.fields.abv'), 'abv'),
            Number::make(__('moonshine.product.fields.ibu'), 'ibu'),
            Number::make(__('moonshine.product.fields.plato'), 'plato'),
            Number::make(__('moonshine.product.fields.ebc'), 'ebc'),
        ];
    }
}
