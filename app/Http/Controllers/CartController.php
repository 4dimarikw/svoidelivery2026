<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Domain\Cart\CartManager;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, CartManager $cart): View
    {
        // Eager-load — тот же набор, что и в CatalogController::index()/
        // FavoriteController::index(), иначе N+1 на каждую строку (см.
        // комментарий в product-card.blade.php). Без пагинации — в отличие
        // от каталога/избранного, корзина по своей природе небольшая.
        // Отдаём сами CartItem, не голые Product — цена строки в строчной
        // вёрстке (cart-line.blade.php) берётся из CartItem::amount()
        // (снятый снапшот цены), тот же источник, что CartManager::amount()
        // для общего итога — если бы строки считались по живой
        // $product->price, сумма строк и итог могли бы разъехаться.
        $cartItems = CartItem::query()
            ->whereHas('cart', fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->with([
                'product.manufacturer',
                'product.volume',
                'product.container',
                'product.media',
                'product.beerDetails.beerStyle',
                'product.beerDetails.untappdBeer',
            ])
            ->get();

        return view('pages.cart', [
            'cartItems' => $cartItems,
            'cart' => $cart,
            'amount' => $cart->amount(),
        ]);
    }

    public function increase(Request $request, Product $product, CartManager $cart): RedirectResponse|JsonResponse
    {
        $cart->increment($product);

        return $this->respond($request, $cart, $product, 'cart-item-added');
    }

    public function decrease(Request $request, Product $product, CartManager $cart): RedirectResponse|JsonResponse
    {
        $cart->decrement($product);

        return $this->respond($request, $cart, $product, 'cart-item-removed');
    }

    public function destroy(Request $request, Product $product, CartManager $cart): RedirectResponse|JsonResponse
    {
        $cart->remove($product);

        return $this->respond($request, $cart, $product, 'cart-item-removed');
    }

    public function clear(CartManager $cart): RedirectResponse
    {
        $cart->truncate();

        return redirect()->route('cart.index')->with('status', 'cart-cleared');
    }

    private function respond(Request $request, CartManager $cart, Product $product, string $status): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'quantity' => $cart->quantityOf($product),
                'count' => $cart->count(),
                'amount' => (string) $cart->amount(),
            ]);
        }

        return back()->with('status', $status);
    }
}
