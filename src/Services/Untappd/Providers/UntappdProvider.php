<?php

declare(strict_types=1);

namespace Services\Untappd\Providers;


use Illuminate\Support\ServiceProvider;
use Services\Untappd\Repositories\UntappdInterface;
use Services\Untappd\Repositories\UntappdRepository;

final class UntappdProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UntappdInterface::class, function ($app) {
            return new UntappdRepository();
        });
    }
}
