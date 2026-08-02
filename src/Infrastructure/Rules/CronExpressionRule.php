<?php

namespace Infrastructure\Rules;

use Closure;
use Cron\CronExpression;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class CronExpressionRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Проверяем, что значение является строкой и валидным cron-выражением
        if (! is_string($value) || ! CronExpression::isValidExpression($value)) {
            $fail('Поле :attribute содержит некорректное расписание cron.');
        }
    }
}
