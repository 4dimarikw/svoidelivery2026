<?php

declare(strict_types=1);

namespace Domain\Cart\Providers;

use Domain\Cart\CartManager;
use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CartManager::class);
    }

    public function boot(): void {}
}
