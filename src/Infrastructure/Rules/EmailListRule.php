<?php

namespace Infrastructure\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Список email-адресов через ";" (см. Infrastructure\Settings\SiteSettings::notifyEmails()).
 * Пустая строка/null допустимы — за nullable отвечает правило поля, не это.
 */
class EmailListRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        foreach (explode(';', $value) as $part) {
            $email = trim($part);

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $fail('Поле :attribute содержит некорректный email: "'.$part.'".');

                return;
            }
        }
    }
}
