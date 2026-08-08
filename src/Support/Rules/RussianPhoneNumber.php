<?php

namespace Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class RussianPhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // First, strip all non-numeric characters from the phone number
        $phoneNumber = preg_replace('/[^0-9]/', '', $value);

        // If the number starts with 7 or 8, remove it
        if (mb_strlen($phoneNumber) === 11 && in_array($phoneNumber[0], ['7', '8'])) {
            $phoneNumber = substr($phoneNumber, 1);
        }

        // The number must be exactly 10 digits long
        if (mb_strlen($phoneNumber) !== 10) {
            $fail('Поле :attribute должно быть действительным российским номером телефона.');
        }
    }
}
