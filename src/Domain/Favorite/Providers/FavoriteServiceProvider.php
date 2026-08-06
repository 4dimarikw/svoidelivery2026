<?php

declare(strict_types=1);

namespace Domain\Favorite\Providers;

use Domain\Favorite\FavoriteManager;
use Illuminate\Support\ServiceProvider;

class FavoriteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FavoriteManager::class);
    }

    public function boot(): void {}
}
