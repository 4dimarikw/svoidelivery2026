<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product;

use App\MoonShine\Resources\Product\Pages\ProductDetailPage;
use App\MoonShine\Resources\Product\Pages\ProductIndexPage;
use Domain\Catalog\Models\Product;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Read-only: every fillable field on Product is overwritten on every
 * catalog:import run (PersistProductStage::__invoke() does a full
 * updateOrCreate() keyed on external_code, including forcing status to
 * GeneralSettings::$product_status) — a form here would silently discard
 * admin edits on the next sync.
 * Exists mainly so BelongsTo::make(..., resource: ProductResource::class)
 * has a target to resolve against (from ProductBarcode and elsewhere);
 * Action::VIEW stays enabled so those links render and are clickable.
 *
 * @extends ModelResource<Product, ProductIndexPage, ProductDetailPage, null>
 */
#[Icon('shopping-bag')]
#[Group('Каталог', 'squares-2x2')]
#[Order(0)]
class ProductResource extends ModelResource
{
    protected string $model = Product::class;

    protected string $column = 'name';

    protected bool $detailInModal = true;

    protected array $with = ['category', 'manufacturer', 'volume', 'container'];

    public function getTitle(): string
    {
        return 'Товары';
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->only(Action::VIEW);
    }

    protected function pages(): array
    {
        return [
            ProductIndexPage::class,
            ProductDetailPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'name', 'article', 'external_code'];
    }
}
