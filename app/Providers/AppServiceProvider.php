<?php

namespace App\Providers;

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

        // Illuminate\Pipeline\Pipeline::carry() re-make()s every stage per
        // CSV row, so a transient CategoryRegistry would re-run its
        // Cache::rememberForever() lookup thousands of times per import.
        // Singleton + explicit CategoryRegistry::flush() (see that class)
        // keeps it to one lookup per process/until invalidated.
        $this->app->singleton(CategoryRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
