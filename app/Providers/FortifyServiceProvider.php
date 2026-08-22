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

        // register/password-reset: не в config('fortify.limiters') — Fortify
        // не даёт эти маршруты троттлить штатно, поэтому подключены через
        // App\Http\Middleware\ThrottleAuthForms (config('fortify.middleware')
        // ниже). По identity и по IP отдельными Limit — один атакующий не
        // обходит лимит сотней разных email с одного хоста.
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinutes(
                config('security.register_throttle.decay_minutes'),
                config('security.register_throttle.per_identity'),
            )->by(self::identityKey($request).'|'.$request->ip())
                ->response(self::throttleResponse('email', 'account.security.register_throttled')),

            Limit::perMinutes(
                config('security.register_throttle.decay_minutes'),
                config('security.register_throttle.per_ip'),
            )->by($request->ip())
                ->response(self::throttleResponse('email', 'account.security.register_throttled')),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinutes(
                config('security.password_reset_throttle.decay_minutes'),
                config('security.password_reset_throttle.per_identity'),
            )->by(self::identityKey($request).'|'.$request->ip())
                ->response(self::throttleResponse('email', 'account.security.password_throttled')),

            Limit::perMinutes(
                config('security.password_reset_throttle.decay_minutes'),
                config('security.password_reset_throttle.per_ip'),
            )->by($request->ip())
                ->response(self::throttleResponse('email', 'account.security.password_throttled')),
        ]);

        // Единственный из четырёх лимитер, который Fortify поддерживает
        // штатно (vendor/laravel/fortify/routes/routes.php:40,98-100,
        // config('fortify.limiters.verification', '6,1')) — middleware не
        // нужен, достаточно назвать лимитер в config/fortify.php.
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinutes(
            config('security.verification_throttle.decay_minutes'),
            config('security.verification_throttle.per_user'),
        )->by($request->user()?->getAuthIdentifier().'|'.$request->ip())->response(
            fn () => back()->with('status', 'verification-throttled')
        ));

        // POST /user/confirm-password — ключ по user_id (email в этой форме
        // не передаётся), ошибка на поле 'password' (то же поле, на которое
        // ConfirmablePasswordController кладёт ошибку неверного пароля).
        RateLimiter::for('password-confirm', fn (Request $request) => Limit::perMinutes(
            config('security.password_confirm_throttle.decay_minutes'),
            config('security.password_confirm_throttle.per_user'),
        )->by($request->user()?->getAuthIdentifier().'|'.$request->ip())
            ->response(self::throttleResponse('password', 'account.security.password_throttled')));
    }

    /**
     * Ключ троттла по email — транслитерация та же, что у лимитера 'login'
     * (FortifyServiceProvider выше), для единообразия регистронезависимого
     * ключа.
     */
    private static function identityKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')));
    }

    /**
     * Общий ->response() для register/password-reset — тот же приём, что у
     * лимитера 'login': без него троттлинг отдал бы голый 429. Свой
     * переводной ключ на каждый лимитер, не auth.throttle — тот текст
     * («Слишком много попыток входа») был бы неверен на этих формах.
     */
    private static function throttleResponse(string $field, string $translationKey): \Closure
    {
        return fn (Request $request, array $headers) => back()
            ->withInput($request->except(['password', 'password_confirmation']))
            ->withErrors([$field => __($translationKey, [
                'seconds' => $headers['Retry-After'] ?? 60,
            ])]);
    }
}
