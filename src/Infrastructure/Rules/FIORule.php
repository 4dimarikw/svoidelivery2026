<?php

namespace Infrastructure\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class FIORule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Проверка на пустое значение
        if (empty($value) || ! is_string($value)) {
            $fail('Поле :attribute обязательно для заполнения.');

            return;
        }

        // Удаляем лишние пробелы
        $value = trim($value);

        // Проверка минимальной длины (минимум 2 символа)
        if (mb_strlen($value) < 2) {
            $fail('Поле :attribute должно содержать минимум 2 символа.');

            return;
        }

        // Проверка максимальной длины (обычно до 50 символов)
        if (mb_strlen($value) > 50) {
            $fail('Поле :attribute не должно превышать 50 символов.');

            return;
        }

        // Проверка на кириллицу, дефисы и пробелы.
        // Допускаются буквы (А-Я, а-я, Ёё), дефисы и пробелы
        if (! preg_match('/^[А-ЯЁа-яё\s\-]+$/u', $value)) {
            $fail('Поле :attribute должно содержать только кириллические буквы, пробелы и дефисы.');

            return;
        }

        // Проверка, что поле не состоит только из пробелов или дефисов
        if (preg_match('/^[\s\-]+$/u', $value)) {
            $fail('Поле :attribute должно содержать буквы.');

            return;
        }

        // Проверка на несколько пробелов подряд
        if (preg_match('/\s{2,}/', $value)) {
            $fail('Поле :attribute не должно содержать несколько пробелов подряд.');

            return;
        }

        // Проверка, что поле начинается с буквы
        if (! preg_match('/^[А-ЯЁа-яё]/u', $value)) {
            $fail('Поле :attribute должно начинаться с буквы.');

            return;
        }
    }
}
