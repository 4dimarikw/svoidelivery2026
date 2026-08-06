<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use Domain\Catalog\Filters\FilterManager;
use Domain\Catalog\Models\Product;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CatalogController extends Controller
{
    // CatalogFilterRequest остаётся типизированным параметром чисто ради
    // валидации — Laravel валидирует его при резолве параметра метода, ДО
    // выполнения тела контроллера, так что невалидный categories[]=999999
    // всё ещё 422/редиректит, даже несмотря на то что $filters->apply()
    // ниже читает request() напрямую, а не провалидированные данные.
    public function index(CatalogFilterRequest $request, FilterManager $filters): View|Response
    {
        $products = $filters->apply(
            Product::query()
                ->published()
                // 'media' — иначе $product->thumb (Product::resolveMediaUrl()) даёт
                // по запросу getFirstMedia() на каждую карточку. 'beerDetails.*' —
                // карточка показывает стиль/abv/ibu/plato/ebc/рейтинг Untappd для
                // пива; beerDetails есть не у всех товаров (аксессуары), но
                // eager-load всё равно нужен, иначе N+1 по каждой карточке.
                ->with(['manufacturer', 'volume', 'container', 'media', 'beerDetails.beerStyle', 'beerDetails.untappdBeer'])
            // Порядок сортировки задаёт SortFilter внутри пайплайна выше
            // (ProductBuilder::sorted()) — здесь больше нет хардкода.
        )
            ->paginate(24)
            ->withQueryString();

        // Догрузка следующей страницы Alpine'ом (см. resources/js/catalog.js):
        // фрагмент вместо полной страницы, ссылка на следующую страницу — в заголовке,
        // по аналогии с X-Redirect в ui.js.
        if ($request->hasHeader('X-Catalog-Partial')) {
            return response()
                ->view('pages.catalog._cards', ['products' => $products])
                ->header('X-Next-Page', (string) $products->nextPageUrl());
        }

        return view('pages.home', [
            'products' => $products,
            'filters' => $filters->items(),
        ]);
    }
}
