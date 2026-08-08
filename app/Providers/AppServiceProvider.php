<?php

namespace App\Providers;

use Domain\Cart\Providers\CartServiceProvider;
use Domain\Catalog\Filters\AbvRangeFilter;
use Domain\Catalog\Filters\BeerStyleFilter;
use Domain\Catalog\Filters\CategoryFilter;
use Domain\Catalog\Filters\ContainerFilter;
use Domain\Catalog\Filters\FilterManager;
use Domain\Catalog\Filters\IbuRangeFilter;
use Domain\Catalog\Filters\InStockFilter;
use Domain\Catalog\Filters\ManufacturerFilter;
use Domain\Catalog\Filters\PriceRangeFilter;
use Domain\Catalog\Filters\SearchFilter;
use Domain\Catalog\Filters\SortFilter;
use Domain\Catalog\Filters\VolumeFilter;
use Domain\Favorite\Providers\FavoriteServiceProvider;
use Domain\Order\Providers\OrderServiceProvider;
use Illuminate\Support\ServiceProvider;
use Services\CatalogImport\CategoryRegistry;
use Services\Untappd\Providers\UntappdProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(
            UntappdProvider::class
        );

        $this->app->register(
            FavoriteServiceProvider::class
        );

        $this->app->register(
            CartServiceProvider::class
        );

        $this->app->register(
            OrderServiceProvider::class
        );

        // Illuminate\Pipeline\Pipeline::carry() re-make()s every stage per
        // CSV row, so a transient CategoryRegistry would re-run its
        // Cache::rememberForever() lookup thousands of times per import.
        // Singleton + explicit CategoryRegistry::flush() (see that class)
        // keeps it to one lookup per process/until invalidated.
        $this->app->singleton(CategoryRegistry::class);

        $this->app->singleton(FilterManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Порядок = визуальный порядок в pages/catalog/_filters.blade.php.
        app(FilterManager::class)->registerFilters([
            new SortFilter,
            new SearchFilter,
            new InStockFilter,
            new CategoryFilter,
            new BeerStyleFilter,
            new ManufacturerFilter,
            new VolumeFilter,
            new ContainerFilter,
            new PriceRangeFilter,
            new AbvRangeFilter,
            new IbuRangeFilter,

        ]);
    }
}
