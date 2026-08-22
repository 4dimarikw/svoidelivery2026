<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Троттлит POST на register.store/password.email/password.update именованными
 * лимитерами (RateLimiter::for, см. FortifyServiceProvider) — те же гарантии,
 * что у ThrottleRequests (Retry-After/X-RateLimit-* заголовки, Limit::response()),
 * не собственная реализация поверх RateLimiter::tooManyAttempts()/hit().
 *
 * Подключается через config('fortify.middleware'), не bootstrap/app.php: это
 * единственный ключ, на который Fortify реально навешивает свою группу
 * маршрутов (vendor/laravel/fortify/routes/routes.php:27), поэтому middleware
 * отрабатывает ровно на маршрутах Fortify и ни на одной публичной странице
 * каталога/корзины. У Fortify нет способа троттлить эти три конкретных
 * маршрута штатно — 'limiters' в config/fortify.php поддерживает только
 * login/two-factor/passkeys/verification.
 */
class ThrottleAuthForms extends ThrottleRequests
{
    /** @var array<string, string> Имя маршрута → имя лимитера (RateLimiter::for). */
    private const LIMITERS = [
        'register.store' => 'register',
        'password.email' => 'password-reset',
        'password.update' => 'password-reset',
        // POST /user/confirm-password — тоже подбор пароля, риск ниже (нужна
        // уже авторизованная сессия), но закрывается той же строкой.
        'password.confirm.store' => 'password-confirm',
    ];

    public function handle($request, Closure $next, ...$args): Response
    {
        $routeName = $request->route()?->getName();
        $limiter = self::LIMITERS[$routeName] ?? null;

        if ($limiter === null || ! $request->isMethod('POST')) {
            return $next($request);
        }

        return parent::handle($request, $next, $limiter);
    }
}
