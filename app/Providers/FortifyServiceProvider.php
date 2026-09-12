<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Requests\LoginRequest;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Support\AuthInput;
use App\Support\NotificationUrls;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\Laravel\Fortify\Http\Requests\LoginRequest::class, LoginRequest::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(NotificationUrls::reset(...));
        VerifyEmail::createUrlUsing(NotificationUrls::verify(...));
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        // Обработчик применяется при включённой двухфакторной аутентификации.
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));

        // Токен сброса приходит параметром маршрута, email — параметром строки запроса; см. ResetPassword.
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'request' => $request,
        ]));

        // При views=true Fortify регистрирует маршрут подтверждения пароля и требует явной привязки его
        // представления.
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        // При включённой emailVerification Fortify требует явной привязки представления для GET /email/verify.
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = self::identityKey($request).'|'.$request->ip();

            // Обработчик ответа возвращает переведённое сообщение auth.throttle при превышении лимита.
            return [
                Limit::perMinute(5)->by('identity|'.$throttleKey)
                    ->response(self::throttleResponse('email', 'auth.throttle')),
                Limit::perMinute(config('security.login_throttle.per_ip'))->by('ip|'.$request->ip())
                    ->response(self::throttleResponse('email', 'auth.throttle')),
            ];
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
        return AuthInput::identity($request);
    }

    /**
     * Общий ->response() для register/password-reset — тот же приём, что у
     * лимитера 'login': без него троттлинг отдал бы голый 429. Свой
     * переводной ключ на каждый лимитер, не auth.throttle — тот текст
     * («Слишком много попыток входа») был бы неверен на этих формах.
     */
    private static function throttleResponse(string $field, string $translationKey): \Closure
    {
        return function (Request $request, array $headers) use ($field, $translationKey) {
            $message = __($translationKey, ['seconds' => $headers['Retry-After'] ?? 60]);

            return $request->wantsJson()
                ? response()->json(['errors' => [$field => [$message]]], 429, $headers)
                : back()->withInput(AuthInput::oldInput($request))
                    ->withErrors([$field => $message]);
        };
    }
}
