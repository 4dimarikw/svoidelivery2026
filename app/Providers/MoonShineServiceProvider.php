<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Support\PermissionCache;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

class MoonShineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PermissionCache::class);
    }

    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        // autoload() обнаруживает ресурсы и страницы в пространстве App\MoonShine, включая Pages внутри
        // ресурсов.
        $core->autoload();
    }
}
