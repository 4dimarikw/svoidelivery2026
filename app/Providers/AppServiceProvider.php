<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
