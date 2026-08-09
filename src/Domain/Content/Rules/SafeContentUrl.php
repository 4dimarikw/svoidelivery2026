<?php

namespace Domain\Content\Rules;

use Closure;
use Domain\Content\Support\SafeContentUrl as UrlResolver;
use Illuminate\Contracts\Validation\ValidationRule;

final class SafeContentUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && $value !== '' && UrlResolver::resolve((string) $value) === null) {
            $fail('Поле :attribute должно содержать http/https URL, локальный путь или якорь.');
        }
    }
}
