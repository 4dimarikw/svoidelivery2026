<?php

declare(strict_types=1);

namespace Domain\Favorite;

use Domain\Favorite\Models\Favorite;
use Domain\Product\Models\ProductVariation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class FavoriteManager
{
    public static function cacheKeyFor(?int $userId): string
    {
        return str('favorite_'.$userId)
            ->slug('_')
            ->value();
    }

    private function cacheKey(): string
    {
        return self::cacheKeyFor(auth()->id());
    }

    public function get()
    {
        return Cache::remember($this->cacheKey(), now()->addHour(), function () {
            return Favorite::query()
                ->when(auth()->check(), fn (Builder $query) => $query->where('user_id', auth()->id()))
                ->with(['productVariation', 'productVariation.product'])
                ->get() ?? false;
        });
    }

    private function forgetCache(): void
    {
        Cache::forget($this->cacheKey());
    }

    public function items(): Collection
    {
        if (! $this->get()) {
            return collect([]);
        }

        return $this->get();
    }

    public function count(): int
    {
        return $this->items()->count();
    }

    public function add(ProductVariation $productVariation): void
    {
        try {
            if ($this->items()->where('product_variation_id', $productVariation->id)->isEmpty()) {
                $favorite = new Favorite;

                $favorite->fill(['product_variation_id' => $productVariation->id, 'user_id' => auth()->id()]);

                $favorite->save();

                $this->forgetCache();
            }
        } catch (\Throwable $e) {
            report($e);

            session()->flash(
                'project_flash',
                ['message' => 'Ошибка на сервере. Попробуйте позже', 'status' => 'danger']
            );
        }
    }

    public function delete(Favorite $item): void
    {
        try {
            $item->delete();

            $this->forgetCache();
        } catch (\Throwable $e) {
            report($e);

            session()->flash(
                'project_flash',
                ['message' => 'Ошибка на сервере. Попробуйте позже', 'status' => 'danger']
            );
        }
    }

    public function truncate(): void
    {
        try {
            if ($this->get()) {
                $this->get()?->delete();
            }

            $this->forgetCache();
        } catch (\Throwable $e) {
            report($e);

            session()->flash(
                'project_flash',
                ['message' => 'Ошибка на сервере. Попробуйте позже', 'status' => 'danger']
            );
        }
    }
}
