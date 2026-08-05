<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CatalogFilterRequest;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(CatalogFilterRequest $request): View|Response
    {
        $products = Product::query()
            ->published()
            // 'media' — иначе $product->thumb (Product::resolveMediaUrl()) даёт
            // по запросу getFirstMedia() на каждую карточку.
            ->with(['manufacturer', 'volume', 'container', 'media'])
            ->inCategories($request->categories())
            ->ofManufacturers($request->manufacturers())
            ->ofVolumes($request->volumes())
            ->ofContainers($request->containers())
            ->inPriceRange($request->priceMin(), $request->priceMax())
            ->onlyInStock($request->inStock())
            ->search($request->searchTerm())
            ->orderByDesc('id')
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
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'manufacturers' => Manufacturer::query()->where('is_active', true)->orderBy('name')->get(),
            'volumes' => Volume::query()->orderBy('milliliters')->get(),
            'containers' => Container::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
