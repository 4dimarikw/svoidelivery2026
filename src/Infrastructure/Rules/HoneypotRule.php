<?php

namespace Infrastructure\Rules;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Honeypot-защита формы регистрации: скрытая приманка ($field) + шифрованный
 * таймер рендера ($timerField), см. <x-ui.honeypot>. Вешается на атрибут
 * 'email' (реально существующее видимое поле) — вешать на website_url/
 * form_loaded_at некуда, эти поля нигде не рендерят <x-ui.error>.
 *
 * DataAwareRule — только так правило получает доступ к соседним полям
 * запроса, а не только к значению своего атрибута.
 */
class HoneypotRule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('security.honeypot.enabled')) {
            return;
        }

        $trapField = (string) config('security.honeypot.field');
        $timerField = (string) config('security.honeypot.timer_field');

        $trapValue = $this->data[$trapField] ?? null;

        // Приманка заполнена — верный признак бота, автозаполнение её не
        // трогает (поле визуально скрыто, autocomplete="off").
        if (is_string($trapValue) && trim($trapValue) !== '') {
            Log::channel('security')->info('honeypot trap filled', [
                'ip' => request()->ip(),
                'route' => request()->route()?->getName(),
            ]);

            $fail(__('account.security.form_rejected'));

            return;
        }

        $timerRaw = $this->data[$timerField] ?? null;

        if (! is_string($timerRaw) || $timerRaw === '') {
            $this->rejectAsBot($fail, 'honeypot timer field missing');

            return;
        }

        try {
            $loadedAt = (int) Crypt::decryptString($timerRaw);
        } catch (DecryptException) {
            // Голый time() бот подделал бы одной строкой — шифрование
            // делает подмену невозможной без ключа приложения.
            $this->rejectAsBot($fail, 'honeypot timer not decryptable');

            return;
        }

        $elapsed = now()->timestamp - $loadedAt;

        if ($elapsed < (int) config('security.honeypot.min_seconds')) {
            $this->rejectAsBot($fail, 'form submitted too fast');

            return;
        }

        if ($elapsed > (int) config('security.honeypot.max_seconds')) {
            // Не бот — протухшая вкладка/реплей. Отдельное, конкретное
            // сообщение, не общее "не удалось отправить форму".
            $fail(__('account.security.form_expired'));
        }
    }

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    private function rejectAsBot(Closure $fail, string $reason): void
    {
        Log::channel('security')->info($reason, [
            'ip' => request()->ip(),
            'route' => request()->route()?->getName(),
        ]);

        // Одно и то же сообщение для всех причин отказа (кроме form_expired
        // выше) — бот не должен понять, какая именно проверка сработала.
        $fail(__('account.security.form_rejected'));
    }
}
