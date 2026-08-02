<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        // autoload() already discovers every ResourceContract/PageContract
        // under the configured namespace (App\MoonShine\..., incl.
        // resources' own Pages\*). Chaining an explicit ->resources([...])/
        // ->pages([...]) call before it — as this used to do — registered
        // the same classes twice (verified via
        // getResources()/getPages() each printing every entry twice).
        $core->autoload();
    }
}
