<?php

namespace Domain\Favorite\Providers;

use Domain\Favorite\FavoriteManager;
use Illuminate\Support\ServiceProvider;

class FavoriteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(
            ActionsServiceProvider::class
        );

        $this->app->singleton(FavoriteManager::class);
    }

    public function boot(): void
    {
    }

}
