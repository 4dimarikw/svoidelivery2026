<?php

use App\Providers\AppServiceProvider;
use App\Providers\ContentCmsIntegrationServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\MoonShineServiceProvider;
use App\Providers\SafeLoggingServiceProvider;

return [
    SafeLoggingServiceProvider::class,
    AppServiceProvider::class,
    ContentCmsIntegrationServiceProvider::class,
    FortifyServiceProvider::class,
    MoonShineServiceProvider::class,
];
