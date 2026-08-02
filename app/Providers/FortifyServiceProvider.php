<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        // Inert with 2FA disabled — kept so enabling it later is a pure
        // config change, no code to rediscover.
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));

        // $request carries the reset token as a route parameter and the
        // email as a query string param (see ResetPassword notification).
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'request' => $request,
        ]));

        // Mandatory even though nothing links here: with 'views' => true,
        // GET /user/confirm-password is registered unconditionally and
        // Fortify has no default ConfirmPasswordViewResponse binding —
        // without this the route 500s.
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        // Same story as confirmPasswordView above: with emailVerification
        // enabled, GET /email/verify is registered unconditionally and
        // Fortify has no default VerifyEmailViewResponse binding.
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            // Without a response callback, ThrottleRequests throws a bare
            // 429 and the translated auth.throttle string is never used.
            return Limit::perMinute(5)->by($throttleKey)->response(
                fn (Request $request, array $headers) => back()
                    ->withInput($request->except('password'))
                    ->withErrors([Fortify::username() => __('auth.throttle', [
                        'seconds' => $headers['Retry-After'] ?? 60,
                    ])])
            );
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
