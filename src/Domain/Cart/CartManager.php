<?php

declare(strict_types=1);

namespace Domain\Cart;

use Closure;
use Domain\Auth\Models\User;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Exceptions\ProductUnavailableException;
use Domain\Catalog\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Support\ValueObjects\Price;

/**
 * Только для авторизованных — у гостя все методы возвращают пустой/нулевой
 * результат без единого запроса к БД (кнопка "Купить" и так видна только
 * авторизованным, см. product-card.blade.php). Никакого Cache-слоя поверх
 * Eloquent — та же логика, что и в Domain\Favorite\FavoriteManager: дешевле
 * мемоизации на один HTTP-запрос, и не рассинхронивается с правками в обход
 * менеджера. `$items` — CartItem текущего пользователя, keyBy('product_id'),
 * без eager-load продукта (это ответственность вызывающего кода — см.
 * CartController::index(), который сам грузит нужные связи).
 */
final class CartManager
{
    private ?Collection $items = null;

    private ?Cart $lockedCart = null;

    private ?Collection $lockedProducts = null;

    /**
     * Единый порядок блокировок для оформления и любых изменений корзины.
     * На каждой попытке транзакции читаем модели заново, включая прогретый кеш.
     */
    public function transaction(Closure $callback, array $productIds = []): mixed
    {
        if ($this->lockedCart !== null) {
            return $callback($this->lockedCart, $this->lockedProducts);
        }

        return DB::transaction(function () use ($callback, $productIds) {
            $this->items = null;

            try {
                User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
                $cart = Cart::query()->firstOrCreate(['user_id' => auth()->id()]);
                $this->lockedCart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
                $this->items = $cart->cartItems()->orderBy('id')->lockForUpdate()->get()->keyBy('product_id');
                $ids = $this->items->keys()->merge($productIds)->unique()->sort()->values();
                $this->lockedProducts = Product::query()->whereKey($ids->all())
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                // Блокирующее чтение получает актуальные флаги даже после ожидания
                // товара при REPEATABLE READ и удерживает их до фиксации операции.
                $this->lockedProducts->load([
                    'category' => fn ($query) => $query->orderBy('id')->sharedLock(),
                    'manufacturer' => fn ($query) => $query->orderBy('id')->sharedLock(),
                ]);

                foreach ($this->items as $item) {
                    $item->setRelation('product', $this->lockedProducts->get($item->product_id));
                }

                return $callback($this->lockedCart, $this->lockedProducts);
            } finally {
                $this->items = null;
                $this->lockedCart = null;
                $this->lockedProducts = null;
            }
        }, 3);
    }

    public function checkoutToken(): string
    {
        return $this->transaction(function (Cart $cart): string {
            if ($cart->checkout_token === null) {
                $cart->checkout_token = (string) Str::uuid();
                $cart->save();
            }

            return $cart->checkout_token;
        });
    }

    private function items(): Collection
    {
        if (! auth()->check()) {
            return collect();
        }

        return $this->items ??= CartItem::query()
            ->whereHas('cart', fn ($query) => $query->where('user_id', auth()->id()))
            ->get()
            ->keyBy('product_id');
    }

    /**
     * Все строки корзины текущего пользователя с догруженным product —
     * то, что нужно Domain\Order\Processes (сборка позиций заказа, проверка
     * наличия, списание остатка), а не только числа (quantityOf/count/amount
     * ниже). loadMissing(), не with() внутри items() — сама items() уже
     * мемоизирована, повторный вызов cartItems() не бьёт по БД второй раз.
     */
    public function cartItems(): Collection
    {
        return $this->items()->loadMissing(['product.category', 'product.manufacturer']);
    }

    public function quantityOf(Product|int $product): int
    {
        $productId = $product instanceof Product ? $product->getKey() : $product;

        return $this->items()->get($productId)?->quantity ?? 0;
    }

    public function has(Product|int $product): bool
    {
        return $this->quantityOf($product) > 0;
    }

    public function count(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function amount(): Price
    {
        return $this->items()->reduce(
            fn (Price $carry, CartItem $item) => $carry->add($item->amount ?? Price::fromMinor(0)),
            Price::fromMinor(0)
        );
    }

    /**
     * Добавляет $by штук товара (или убавляет — decrement() ниже переиспользует
     * этот же метод с отрицательным $by). Цена переснимается с текущей
     * $product->price при каждом изменении количества — отдельного действия
     * "обновить цену в корзине" не требуется. Уход в 0 или ниже удаляет строку
     * целиком — то самое поведение "минус на количестве 1 убирает товар и
     * возвращает «Купить»" из брендбука (design-system.html, §06).
     */
    public function increment(Product $product, int $by = 1): ?CartItem
    {
        if (! auth()->check()) {
            return null;
        }

        return $this->transaction(function (Cart $cart, Collection $products) use ($product, $by): ?CartItem {
            $freshProduct = $products->get($product->getKey());
            if ($freshProduct === null) {
                return null;
            }

            if ($by > 0 && ! $freshProduct->canBePurchased()) {
                if (! $freshProduct->isVisibleOnStorefront()) {
                    throw new ProductUnavailableException(__('catalog.cart.unavailable'));
                }

                return null;
            }

            $item = $this->items->get($product->getKey()) ?? new CartItem([
                'cart_id' => $cart->id,
                'product_id' => $product->getKey(),
            ]);
            $newQuantity = $this->clampQuantity($freshProduct, ($item->exists ? $item->quantity : 0) + $by);

            if ($newQuantity <= 0) {
                if ($item->exists) {
                    $item->delete();
                }
                $this->items->forget($product->getKey());

                return null;
            }

            $item->quantity = $newQuantity;
            $item->price = $freshProduct->price;
            $item->save();
            $this->items->put($product->getKey(), $item);

            return $item;
        }, [$product->getKey()]);
    }

    public function decrement(Product $product, int $by = 1): ?CartItem
    {
        return $this->increment($product, -$by);
    }

    public function remove(Product|int $product): void
    {
        if (! auth()->check()) {
            return;
        }

        $productId = $product instanceof Product ? $product->getKey() : $product;

        $this->transaction(function (Cart $cart) use ($productId): void {
            $cart->cartItems()->where('product_id', $productId)->delete();
            $this->items = null;
        });
    }

    public function truncate(): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->transaction(function (Cart $cart): void {
            $cart->cartItems()->delete();
            $this->items = null;
        });
    }

    /**
     * 0..availableStock(), всегда 0, если товар снят с наличия — клампит
     * количество по фактическому остатку независимо от направления изменения.
     */
    private function clampQuantity(Product $product, int $quantity): int
    {
        return max(0, min($quantity, $product->availableStock()));
    }
}
