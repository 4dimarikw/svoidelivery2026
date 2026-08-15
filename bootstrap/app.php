<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Sluggable\Exceptions\StaleSelfHealingUrl;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Self-healing slug (spatie/laravel-sluggable) — не ошибка, а штатный
        // способ сделать редирект на канонический slug (у исключения свой
        // render(), см. StaleSelfHealingUrl::render()); без dontReport() оно
        // засоряло бы логи ложным ERROR на каждый заход по устаревшей ссылке.
        $exceptions->dontReport(StaleSelfHealingUrl::class);
    })->create();
