<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Тот же набор связей, что и каталог (CatalogController::index()) — плюс
     * category, которой каталогу не нужно (там категория участвует только
     * фильтром по id, здесь — ещё и хлебными крошками).
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
     * поэтому черновики/архив всё ещё резолвятся моделью — отсекаем их здесь
     * же, как published() отсекает их в каталоге. «Нет в наличии» — не тот
     * случай: такие товары каталог показывает (published(), не active()),
     * страница товара должна быть доступна точно так же.
     */
    public function show(Product $product): View
    {
        abort_unless($product->status === ProductStatus::PUBLISHED, 404);

        $product->load('beerDetails');

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
            ->published()
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
