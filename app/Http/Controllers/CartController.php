<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Domain\Cart\CartManager;
use Domain\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(CartManager $cart): View
    {
        // CartManager::cartItems() — тот же мемоизированный на HTTP-запрос
        // $items, что читают $cart->count()/->amount() ниже и шапка
        // (header.blade.php, mobile-nav.blade.php): отдельный CartItem::query()
        // здесь означал бы второй SELECT по cart_items поверх уже
        // прогретого менеджера. loadMissing() дозагружает только то, чего
        // ещё нет — product уже загружен внутри cartItems(), тут довешиваем
        // только relations, нужные cart-line.blade.php/product-placeholder.blade.php.
        // Отдаём сами CartItem, не голые Product — цена строки берётся из
        // CartItem::amount() (снятый снапшот цены), тот же источник, что
        // CartManager::amount() для общего итога — если бы строки
        // считались по живой $product->price, сумма строк и итог могли бы
        // разъехаться.
        $cartItems = $cart->cartItems()->loadMissing([
            'product.volume',
            'product.container',
            'product.media',
            'product.manufacturer',
            'product.beerDetails.beerStyle',
        ]);

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
