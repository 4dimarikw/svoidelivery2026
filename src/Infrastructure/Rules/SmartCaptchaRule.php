<?php

namespace Infrastructure\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

class SmartCaptchaRule implements ValidationRule
{
    private const string VALIDATE_URL = 'https://smartcaptcha.cloud.yandex.ru/validate';

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
            $fail('Подтвердите, что вы не робот.');

            return;
        }

        $secret = (string) config('security.smart_captcha.server_key', '');

        if ($secret === '') {
            Log::channel('security')->warning('SmartCaptcha server key not configured');

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('security.smart_captcha.timeout', 2))
                ->post(self::VALIDATE_URL, [
                    'secret' => $secret,
                    'token' => $token,
                    'ip' => request()->ip(),
                ]);
        } catch (Throwable $e) {
            Log::channel('security')->error('SmartCaptcha validation request failed', [
                'message' => $e->getMessage(),
            ]);

            // Fail-open: пропускаем при сбое запроса
            return;
        }

        if (! $response->successful()) {
            Log::channel('security')->warning('SmartCaptcha API returned non-2xx response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Fail-open: пропускаем при ошибке API
            return;
        }

        if ($response->json('status') !== 'ok') {
            Log::channel('security')->warning('SmartCaptcha token rejected', [
                'status' => $response->json('status'),
                'message' => $response->json('message'),
                'ip' => request()->ip(),
            ]);
            $fail('Проверка капчи не пройдена. Попробуйте ещё раз.');
        }
    }
}
