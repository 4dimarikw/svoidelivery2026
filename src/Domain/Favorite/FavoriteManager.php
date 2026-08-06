<?php

declare(strict_types=1);

namespace Domain\Favorite;

use Domain\Catalog\Models\Product;
use Domain\Favorite\Models\Favorite;
use Illuminate\Support\Collection;

/**
 * Только для авторизованных — у гостя все методы возвращают пустой/false
 * результат без единого запроса к БД. Никакого доп. кэширования поверх
 * Eloquent: выборка id упирается в уникальный индекс (user_id, product_id)
 * и дешевле, чем инвалидация БД-кэша, который к тому же легко рассинхронить
 * с записями, сделанными в обход менеджера (например, из MoonShine).
 * `$ids` — мемоизация на один HTTP-запрos, чтобы карточки каталога (24 шт.
 * на страницу) не бомбили БД повторными SELECT.
 */
final class FavoriteManager
{
    private ?Collection $ids = null;

    /**
     * product_id избранного текущего пользователя.
     */
    public function productIds(): Collection
    {
        if (! auth()->check()) {
            return collect();
        }

        return $this->ids ??= Favorite::query()
            ->where('user_id', auth()->id())
            ->pluck('product_id');
    }

    public function has(Product|int $product): bool
    {
        $productId = $product instanceof Product ? $product->getKey() : $product;

        return $this->productIds()->contains($productId);
    }

    public function count(): int
    {
        return $this->productIds()->count();
    }

    public function add(Product $product): void
    {
        if (! auth()->check()) {
            return;
        }

        Favorite::query()->firstOrCreate([
            'user_id' => auth()->id(),
            'product_id' => $product->getKey(),
        ]);

        $this->ids = null;
    }

    public function remove(Product $product): void
    {
        if (! auth()->check()) {
            return;
        }

        Favorite::query()
            ->where('user_id', auth()->id())
            ->where('product_id', $product->getKey())
            ->delete();

        $this->ids = null;
    }

    /**
     * @return bool Новое состояние (true — добавлено, false — удалено).
     */
    public function toggle(Product $product): bool
    {
        if ($this->has($product)) {
            $this->remove($product);

            return false;
        }

        $this->add($product);

        return true;
    }

    public function truncate(): void
    {
        if (! auth()->check()) {
            return;
        }

        Favorite::query()->where('user_id', auth()->id())->delete();

        $this->ids = null;
    }
}
