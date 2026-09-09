<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Тот же набор связей, что и каталог (CatalogController::index()).
     * Категория нужна правилу покупки и хлебным крошкам.
     */
    private const EAGER_LOAD = [
        'category', 'manufacturer', 'volume', 'container', 'media',
        'beerDetails.beerStyle', 'beerDetails.untappdBeer',
    ];

    /**
     * {product} резолвится self-healing-биндингом из HasSlug
     * (Product::getSlugOptions()) — устаревший slug сам 308-редиректит на
     * актуальный URL, роуту/контроллеру ничего доделывать не нужно.
     *
     * resolveRouteBinding() внутри HasSlug идёт через newQuery() без скоупов,
     * поэтому видимость проверяем здесь: публикация и активные категория/
     * производитель. Остаток не влияет на доступность страницы товара.
     */
    public function show(Product $product): View
    {
        $product->load(['category', 'manufacturer', 'beerDetails']);
        abort_unless($product->isVisibleOnStorefront(), 404);

        $similar = $this->similar($product);

        // Общая догрузка связей для product + similar одним проходом —
        // пересекающиеся id (category, beer_style) уходят одним запросом
        // вместо двух (отдельно для product, отдельно для similar).
        Collection::make([$product])->merge($similar)->loadMissing(self::EAGER_LOAD);

        return view('pages.product', [
            'product' => $product,
            'similar' => $similar,
        ]);
    }

    /**
     * Тот же стиль пива в приоритете (точнее категории — «эль» и «лагер» из
     * одной категории «Пиво» разошлись бы иначе), категория — запасной
     * критерий для аксессуаров и товаров без beerDetails.
     */
    private function similar(Product $product): Collection
    {
        $styleId = $product->beerDetails?->beer_style_id;

        return Product::query()
            ->visibleOnStorefront()
            ->when(
                $styleId,
                fn ($query) => $query->ofBeerStyles([$styleId]),
                fn ($query) => $query->inCategories([$product->category_id]),
            )
            ->whereKeyNot($product->getKey())
            ->inRandomOrder()
            ->limit(5)
            ->get();
    }
}
