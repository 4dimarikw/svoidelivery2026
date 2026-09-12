<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustedHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production')) {
            $hosts = [parse_url(config('app.url'), PHP_URL_HOST), ...config('security.trusted_hosts', [])];
            $hosts = array_map(fn ($host) => strtolower((string) $host), $hosts);
            abort_unless(in_array(strtolower($request->getHost()), $hosts, true), 400, 'Untrusted host.');
        }

        return $next($request);
    }
}
