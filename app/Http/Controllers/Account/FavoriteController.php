<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Domain\Catalog\Models\Product;
use Domain\Favorite\FavoriteManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        // Пагинация по Favorite, не по Product — иначе теряется порядок
        // "недавно добавленные первыми". whereHas('product', published())
        // прячет товары, снятые с публикации после добавления в избранное,
        // не трогая саму запись избранного. Eager-load — тот же набор, что
        // и в CatalogController::index(), иначе N+1 на каждую карточку
        // (см. комментарий в product-card.blade.php).
        $favorites = $request->user()->favorites()
            ->latest()
            ->whereHas('product', fn ($query) => $query->published())
            ->with([
                'product.manufacturer',
                'product.volume',
                'product.container',
                'product.media',
                'product.beerDetails.beerStyle',
                'product.beerDetails.untappdBeer',
            ])
            ->paginate(24);

        // through() сохраняет пагинатор (ссылки, total, ...), просто меняя
        // элементы — pages/catalog/_cards.blade.php ждёт коллекцию Product.
        $products = $favorites->through(fn ($favorite) => $favorite->product);

        return view('account.favorites.index', ['products' => $products]);
    }

    public function toggle(Request $request, Product $product, FavoriteManager $favorites): RedirectResponse|JsonResponse
    {
        $favorited = $favorites->toggle($product);

        if ($request->wantsJson()) {
            return response()->json([
                'favorited' => $favorited,
                'count' => $favorites->count(),
            ]);
        }

        return back()->with('status', $favorited ? 'favorite-added' : 'favorite-removed');
    }

    public function destroy(FavoriteManager $favorites): RedirectResponse
    {
        $favorites->truncate();

        return redirect()->route('account.favorites.index')->with('status', 'favorites-cleared');
    }
}
