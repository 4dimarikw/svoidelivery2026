<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($next($request), $request);
    }

    public static function apply(Response $response, Request $request): Response
    {
        $admin = str_starts_with($request->route()?->getName() ?? '', 'moonshine.')
            || $request->is(trim(config('moonshine.prefix', 'admin'), '/').'/*');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $policy = "base-uri 'self'; object-src 'none'";
        if ($admin) {
            $policy .= "; frame-ancestors 'none'";
            $response->headers->set('X-Frame-Options', 'DENY');
        }
        $response->headers->set('Content-Security-Policy', $policy);
        if (! $admin) {
            // Diagnostic only: ordinary Alpine currently requires eval/inline expressions.
            $response->headers->set('Content-Security-Policy-Report-Only',
                "script-src 'self' https://telegram.org https://smartcaptcha.cloud.yandex.ru https://captcha-api.yandex.ru; frame-ancestors 'self' https://web.telegram.org");
        }
        if (app()->environment('production') && $request->isSecure()
            && parse_url(config('app.url'), PHP_URL_SCHEME) === 'https') {
            $response->headers->set('Strict-Transport-Security', 'max-age='.config('security.https_hsts_max_age'));
        }

        return $response;
    }
}
