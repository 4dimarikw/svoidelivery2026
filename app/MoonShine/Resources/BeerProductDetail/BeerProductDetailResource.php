<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\BeerProductDetail;

use App\MoonShine\Resources\BeerProductDetail\Pages\BeerProductDetailFormPage;
use App\MoonShine\Resources\BeerProductDetail\Pages\BeerProductDetailIndexPage;
use Domain\Catalog\Models\BeerProductDetail;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Support\Attributes\Icon;

/**
 * Не самостоятельный раздел меню (#[SkipMenu]) — существует только как цель
 * RelationRepeater::make(..., 'beerDetails')->fields(...) в ProductFormPage/
 * ProductDetailPage: MoonShine\Laravel\Fields\Relationships\ModelRelationField
 * требует ModelResource для любой relation-field, а BeerProductDetail
 * (1:1 с Product, PK = product_id) собственного раздела в каталоге не имеет —
 * пивные характеристики редактируются только внутри карточки товара.
 *
 * @extends ModelResource<BeerProductDetail, BeerProductDetailIndexPage, BeerProductDetailFormPage, null>
 */
#[Icon('beaker')]
#[SkipMenu]
class BeerProductDetailResource extends ModelResource
{
    protected string $model = BeerProductDetail::class;

    protected string $column = 'abv';

    protected function pages(): array
    {
        return [
            BeerProductDetailIndexPage::class,
            BeerProductDetailFormPage::class,
        ];
    }
}
