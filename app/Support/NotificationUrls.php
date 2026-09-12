<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;

final class NotificationUrls
{
    private static function generator(): UrlGenerator
    {
        // A fresh instance is required: cloning retains RouteUrlGenerator's old origin.
        $origin = rtrim(config('app.url'), '/');
        $urls = new UrlGenerator(app('router')->getRoutes(), Request::create($origin));
        $urls->setKeyResolver(fn () => config('app.key'));
        $urls->forceRootUrl($origin);
        $urls->forceScheme(parse_url($origin, PHP_URL_SCHEME));

        return $urls;
    }

    public static function reset($user, string $token): string
    {
        return self::generator()->route('password.reset', [
            'token' => $token, 'email' => $user->getEmailForPasswordReset(),
        ]);
    }

    public static function verify($user): string
    {
        return self::generator()->temporarySignedRoute('verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)), [
                'id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification()),
            ]);
    }
}
