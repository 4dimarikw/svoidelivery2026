<?php

declare(strict_types=1);

namespace Domain\Cart;

use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Catalog\Models\Product;
use Illuminate\Support\Collection;
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

        $cart = Cart::query()->firstOrCreate(['user_id' => auth()->id()]);

        $item = CartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->getKey(),
        ]);

        $newQuantity = $this->clampQuantity($product, ($item->exists ? $item->quantity : 0) + $by);

        $this->items = null;

        if ($newQuantity <= 0) {
            if ($item->exists) {
                $item->delete();
            }

            return null;
        }

        $item->quantity = $newQuantity;
        $item->price = $product->price;
        $item->save();

        return $item;
    }

    public function decrement(Product $product, int $by = 1): void
    {
        $this->increment($product, -$by);
    }

    public function remove(Product|int $product): void
    {
        if (! auth()->check()) {
            return;
        }

        $productId = $product instanceof Product ? $product->getKey() : $product;

        CartItem::query()
            ->where('product_id', $productId)
            ->whereHas('cart', fn ($query) => $query->where('user_id', auth()->id()))
            ->delete();

        $this->items = null;
    }

    public function truncate(): void
    {
        if (! auth()->check()) {
            return;
        }

        CartItem::query()
            ->whereHas('cart', fn ($query) => $query->where('user_id', auth()->id()))
            ->delete();

        $this->items = null;
    }

    /**
     * 0..stock_quantity, всегда 0, если товар снят с наличия — клампит
     * количество по фактическому остатку независимо от направления изменения.
     */
    private function clampQuantity(Product $product, int $quantity): int
    {
        $stock = $product->in_stock ? max(0, (int) $product->stock_quantity) : 0;

        return max(0, min($quantity, $stock));
    }
}
