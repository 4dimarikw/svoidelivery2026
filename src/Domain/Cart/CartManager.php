<?php

declare(strict_types=1);

namespace Domain\Cart;

use Domain\Cart\Contracts\CartIdentityStorageContract;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Cart\StorageIdentities\FakeIdentityStorage;
use Domain\Catalog\Models\Category;
use Domain\Order\Models\Order;
use Domain\Product\Models\ProductVariation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use ReflectionException;
use Support\ValueObjects\Price;

final class CartManager
{
    public function __construct(
        protected CartIdentityStorageContract $identityStorage
    ) {}

    /**
     * @throws ReflectionException
     */
    public static function fake(): void
    {
        app()->bind(CartIdentityStorageContract::class, FakeIdentityStorage::class);
    }

    private function cacheKey(): string
    {

        return auth()->check() ? str('cart_'.(auth()->user()->id * 123321))
            ->slug('_')
            ->value() : str('cart_'.$this->identityStorage->get())
            ->slug('_')
            ->value();
    }

    public function forgetCache(): void
    {
        Cache::forget($this->cacheKey());
    }

    public function updateStorageId(string $old, string $current): void
    {
        Cart::query()
            ->where('storage_id', $old)
            ->update($this->storeData($current));
    }

    private function storeData(string $id): array
    {
        $data = [
            'storage_id' => $id,
        ];

        if (auth()->check()) {
            $data['user_id'] = auth()->id();
        }

        return $data;
    }

    public function add(ProductVariation $productVariation, int $quantity): Cart
    {
        $cart = Cart::query()
            ->updateOrCreate([
                'user_id' => auth()->id(),
            ], $this->storeData($this->identityStorage->get()));

        $cart->cartItems()->updateOrCreate([
            'product_variation_id' => $productVariation->getKey(),
        ], [
            'price' => $productVariation->price->value(),
            'quantity' => $quantity,
        ]);

        $this->forgetCache();

        return $cart;
    }

    public function repeatOrder(int $orderId, $replace): Cart
    {
        if ($replace) {
            $this->truncate();
        }

        $cart = Cart::query()
            ->updateOrCreate([
                'user_id' => auth()->id(),
            ], $this->storeData($this->identityStorage->get()));

        $order = Order::with('orderItems.productVariation')->find($orderId);

        $order->orderItems->each(function ($item) use ($cart) {

            $cartItem = $cart->cartItems()
                ->where('product_variation_id', $item->productVariation->getKey())
                ->first();

            $currentPrice = $item->productVariation->price->value();

            if ($cartItem) { // Если товар уже есть - увеличиваем количество
                $newQuantity = $cartItem->quantity + $item->quantity;
                $quantity = $newQuantity > $item->productVariation->stock_quantity ? $item->productVariation->stock_quantity : $newQuantity;

                $cartItem->update(['quantity' => $quantity, 'price' => $currentPrice]);
            } else {
                $quantity = $item->quantity > $item->productVariation->stock_quantity ? $item->productVariation->stock_quantity : $item->quantity;
                $cart->cartItems()->create([
                    'product_variation_id' => $item->productVariation->getKey(),
                    'price' => $item->productVariation->price->value(),
                    'quantity' => $quantity,
                ]);
            }
        });

        $this->forgetCache();

        return $cart;
    }

    public function quantity(CartItem $cartItem, int $quantity = 1): void
    {
        if ($quantity == 0) {
            $this->delete($cartItem);
        } else {
            $cartItem->update([
                'quantity' => $quantity,
            ]);
        }

        $this->forgetCache();
    }

    public function delete(CartItem $cartItem): void
    {
        $cartItem->delete();

        $this->forgetCache();
    }

    public function truncate(): void
    {
        if ($this->get()) {
            $this->get()?->delete();
        }

        $this->forgetCache();
    }

    //    public function items(): Collection
    //    {
    //        if (!$this->get()) {
    //            return collect();
    //        }
    //        return CartItem::query()
    //            ->with(['productVariation', 'productVariation.product'])
    //            ->whereBelongsTo($this->get())
    //            ->get();
    //    }
    //
    //
    //    public function cartItems(): Collection
    //    {
    //        if (!$this->get()) {
    //            return collect();
    //        }
    //
    //        return $this->get()->cartItems;
    //    }

    public function cartItems(): Collection|\Illuminate\Database\Eloquent\Collection
    {
        return $this->get()?->cartItems ?? collect([]);
    }

    /**
     * Позиции корзины без товаров категории equipment и всех её потомков.
     */
    public function cartItemsWithoutEquipment(): Collection|\Illuminate\Database\Eloquent\Collection
    {
        $items = $this->cartItems();

        $equipmentId = Category::query()
            ->where('slug', config('equipment_import.site_category', 'equipment'))
            ->value('id');

        if (! $equipmentId || $items->isEmpty()) {
            return $items;
        }

        // id категории equipment + все потомки (descendants-and-self)
        $equipmentCategoryIds = Category::descendantsOf($equipmentId, ['*'], true)->pluck('id');

        // product нужен для category_id; догрузим пачкой (без N+1)
        $items->loadMissing('productVariation.product');

        return $items
            ->reject(fn (CartItem $item) => $equipmentCategoryIds->contains(
                $item->productVariation?->product?->category_id
            ))
            ->values();
    }

    public function count(): int
    {
        return $this->cartItems()->sum(function ($item) {
            return $item->quantity;
        });
    }

    public function amount(): Price
    {
        return Price::make(
            $this->cartItems()->sum(function ($cartItem) {
                return $cartItem->amount->raw();
            }),
            false
        );
    }

    public function amountWithoutEquipment(): Price
    {
        return Price::make(
            $this->cartItemsWithoutEquipment()->sum(function ($cartItem) {
                return $cartItem->amount->raw();
            }),
            false
        );
    }

    public function get()
    {
        return Cache::remember($this->cacheKey(), now()->addHour(), function () {
            $cart = Cart::query()
                ->with(['cartItems.productVariation'])
                ->where('user_id', auth()->id())
                ->first();

            if (! $cart) {
                return false;
            }

            // Скрыть позиции, чья вариация soft-deleted — иначе сериализация
            // productVariation падает на null. Не удаляем: при восстановлении
            // вариации позиция вернётся.
            $cart->setRelation(
                'cartItems',
                $cart->cartItems->filter(
                    fn (CartItem $item) => $item->productVariation !== null
                )->values()
            );

            return $cart;
        });
    }

    public function isIn(int $variationId): bool
    {
        if (! $this->get()) {
            return false;
        }

        return $this->get()->cartItems
            ->where('product_variation_id', $variationId)
            ->isNotEmpty();
    }
}
