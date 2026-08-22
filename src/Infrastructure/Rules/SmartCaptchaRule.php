<?php

namespace Infrastructure\Rules;

use App\Events\Security\CaptchaFailOpen;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

class SmartCaptchaRule implements ValidationRule
{
    private const string VALIDATE_URL = 'https://smartcaptcha.cloud.yandex.ru/validate';

    // Без этого свойства Laravel пропускает правило, когда значение поля —
    // пустая строка (Validator::presentOrRuleIsImplicit() смотрит именно на
    // instanceof ImplicitRule, к которому это свойство и приводит через
    // InvokableValidationRule::make()) — не присланный токен капчи проходил
    // бы валидацию молча, а ветка ниже с $token === '' была бы недостижима.
    public bool $implicit = true;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('security.smart_captcha.enabled')) {
            return;
        }

        $token = is_string($value) ? trim($value) : '';

        if ($token === '') {
            Log::channel('security')->info('smartcaptcha token missing', [
                'ip' => request()->ip(),
                'route' => request()->route()?->getName(),
            ]);

            $fail(__('account.security.captcha_required'));

            return;
        }

        $secret = (string) config('security.smart_captcha.server_key', '');

        if ($secret === '') {
            // Осознанный fail-open: пустой server_key — обычно значит, что
            // капча ещё не настроена/выключена на проде, а не что кто-то
            // ломает регистрацию. Останавливать её из-за этого не стоит.
            Log::channel('security')->warning('SmartCaptcha server key not configured', [
                'ip' => request()->ip(),
                'route' => request()->route()?->getName(),
            ]);

            $this->reportFailOpen('no_server_key');

            return;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(1)
                ->timeout((int) config('security.smart_captcha.timeout', 2))
                ->post(self::VALIDATE_URL, [
                    'secret' => $secret,
                    'token' => $token,
                    'ip' => request()->ip(),
                ]);
        } catch (Throwable $e) {
            // Осознанный fail-open: сбой стороннего сервиса не должен
            // останавливать регистрацию на сайте (см. docs/auth-security.md).
            Log::channel('security')->error('SmartCaptcha validation request failed', [
                'message' => $e->getMessage(),
                'ip' => request()->ip(),
                'route' => request()->route()?->getName(),
            ]);

            $this->reportFailOpen('request_failed', errorMessage: $e->getMessage());

            return;
        }

        if (! $response->successful()) {
            // Осознанный fail-open — см. комментарий выше.
            Log::channel('security')->warning('SmartCaptcha API returned non-2xx response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'ip' => request()->ip(),
                'route' => request()->route()?->getName(),
            ]);

            $this->reportFailOpen('bad_status', httpStatus: $response->status());

            return;
        }

        if ($response->json('status') !== 'ok') {
            Log::channel('security')->warning('SmartCaptcha token rejected', [
                'status' => $response->json('status'),
                'message' => $response->json('message'),
                'ip' => request()->ip(),
                'route' => request()->route()?->getName(),
            ]);
            $fail(__('account.security.captcha_failed'));
        }
    }

    /**
     * security.log никто не читает — постоянно сломанный server_key даёт
     * молчаливое отключение защиты от ботов. Cache::add душит повтор на 5
     * минут по причине: правило срабатывает на каждую регистрацию, событие
     * в event_logs не должно.
     */
    private function reportFailOpen(string $reason, ?int $httpStatus = null, ?string $errorMessage = null): void
    {
        if (Cache::add("captcha-fail-open:{$reason}", true, now()->addMinutes(5))) {
            event(new CaptchaFailOpen($reason, $httpStatus, $errorMessage));
        }
    }
}
