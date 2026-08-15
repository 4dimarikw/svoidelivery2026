<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Domain\Cart\CartManager;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CartController extends Controller
{
    public function index(CartManager $cart): Response
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

        // no-store — не про XSS/приватность, про историю навигации: браузер
        // по спецификации (RFC 9111 §4.2.4) вправе переиспользовать
        // сохранённый ответ при переходе кнопками «Назад»/«Вперёд» ДАЖЕ
        // если Cache-Control: no-cache (то, что Laravel отдаёт по
        // умолчанию для обычного view()) — тот же no-cache не спасает от
        // устаревшего количества после ajax-изменений в корзине
        // (cart-stepper.blade.php), которые эту историю-в-памяти не
        // затрагивают. no-store — единственная директива, закрывающая
        // именно этот кейс; на bfcache (другой механизм — там participates
        // JS/DOM целиком) она не всегда влияет, для него отдельно
        // pageshow/persisted в resources/js/app.js.
        return response()
            ->view('pages.cart', [
                'cartItems' => $cartItems,
                'cart' => $cart,
                'amount' => $cart->amount(),
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function increase(Request $request, Product $product, CartManager $cart): RedirectResponse|JsonResponse
    {
        $item = $cart->increment($product);

        return $this->respond($request, $cart, $product, 'cart-item-added', $item);
    }

    public function decrease(Request $request, Product $product, CartManager $cart): RedirectResponse|JsonResponse
    {
        $item = $cart->decrement($product);

        return $this->respond($request, $cart, $product, 'cart-item-removed', $item);
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

    private function respond(Request $request, CartManager $cart, Product $product, string $status, ?CartItem $item = null): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'quantity' => $cart->quantityOf($product),
                'count' => $cart->count(),
                'amount' => (string) $cart->amount(),
                // Сумма конкретной строки (price × quantity), не общий итог
                // выше — cart-line.blade.php реактивно обновляет её при
                // +/− в степпере (см. resources/js/cart.js). null у destroy()
                // и при уходе количества в 0 — строка в обоих случаях
                // убирается целиком, ей эта сумма уже не нужна.
                'lineAmount' => $item ? (string) $item->amount : null,
            ]);
        }

        return back()->with('status', $status);
    }
}
