<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Product;

use App\MoonShine\Resources\Product\Pages\ProductDetailPage;
use App\MoonShine\Resources\Product\Pages\ProductFormPage;
use App\MoonShine\Resources\Product\Pages\ProductIndexPage;
use Domain\Catalog\Models\Product;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Товар редактируется из админки, но не полностью: catalog:import
 * (PersistProductStage::__invoke()) при повторном запуске обновляет только
 * price/stock_quantity/in_stock у уже существующего товара — эти поля всегда
 * возвращаются к состоянию 1С на следующем синке, остальные (name/
 * description/классификация/status/media/пивные детали/штрихкоды)
 * принадлежат админке. `flags` заполняется импортом только при создании
 * товара (см. ResolveFlagsStage/PersistProductStage) и в этой форме не
 * редактируется — чисто backend-поле. Delete/MassDelete отключены: товар из
 * 1С нельзя удалить руками, для вывода из продажи есть status = archived.
 *
 * @extends ModelResource<Product, ProductIndexPage, ProductFormPage, ProductDetailPage>
 */
#[Icon('shopping-bag')]
#[Group('moonshine.group.catalog', 'squares-2x2', translatable: true)]
#[Order(0)]
class ProductResource extends ModelResource
{
    protected string $model = Product::class;

    protected bool $withPolicy = true;

    protected string $column = 'article';

    protected bool $detailInModal = true;

    protected array $with = ['category', 'manufacturer', 'volume', 'container'];

    public function getTitle(): string
    {
        return __('moonshine.product.title');
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::DELETE, Action::MASS_DELETE);
    }

    protected function pages(): array
    {
        return [
            ProductIndexPage::class,
            ProductFormPage::class,
            ProductDetailPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'name', 'article', 'external_code'];
    }
}
