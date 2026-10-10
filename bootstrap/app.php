<?php

use App\Http\Middleware\SecureMoonShineActions;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustedHosts;
use App\Http\Middleware\TrustedProxies;
use Dimarik\LaravelSeo\Http\Middleware\PreventSiteIndexing;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Spatie\Sluggable\Exceptions\StaleSelfHealingUrl;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(PreventSiteIndexing::class);
        $middleware->replace(TrustProxies::class, TrustedProxies::class);
        $middleware->append([TrustedHosts::class, SecurityHeaders::class]);
        $middleware->web(append: [SecureMoonShineActions::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['init_data', 'initData', 'hash', 'auth_date', 'authorization', 'token', 'secret']);
        $exceptions->dontFlash(['smart-token', 'form_loaded_at', '_token']);
        $exceptions->respond(fn ($response) => app(PreventSiteIndexing::class)->apply(
            request(),
            SecurityHeaders::apply($response, request()),
        ));
        // Self-healing slug (spatie/laravel-sluggable) — не ошибка, а штатный
        // способ сделать редирект на канонический slug (у исключения свой
        // render(), см. StaleSelfHealingUrl::render()); без dontReport() оно
        // засоряло бы логи ложным ERROR на каждый заход по устаревшей ссылке.
        $exceptions->dontReport(StaleSelfHealingUrl::class);
    })->create();
