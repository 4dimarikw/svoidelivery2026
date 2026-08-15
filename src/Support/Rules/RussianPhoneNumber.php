<?php

namespace Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class RussianPhoneNumber implements ValidationRule
{
    /**
     * Приводит телефон к каноническому виду +7XXXXXXXXXX перед валидацией
     * — используется и в Domain\Order\Requests\OrderRequest, и в
     * Account\ProfileController, чтобы order_customers.phone и
     * user_profiles.phone хранили один формат. Трогает только похожее на
     * телефон (10-11 цифр) — остальное возвращает как есть, чтобы правило
     * ниже само показало понятную ошибку, а old() вернул в поле именно то,
     * что человек ввёл.
     */
    public static function normalize(string $value): string
    {
        // Не трогаем строки с посторонними символами (буквы, эмодзи и
        // т.п.) — иначе preg_replace('/\D/', ...) молча вырежет их вместе
        // с остальным мусором и превратит явную опечатку в номер, который
        // выглядит валидным. Пусть такое ловит сама валидация ниже.
        if (! preg_match('/^[\d\s\-().+]+$/', $value)) {
            return $value;
        }

        $digits = preg_replace('/\D/', '', $value);

        // Только унифицируем уже присутствующий код страны (8XXXXXXXXXX,
        // 7XXXXXXXXXX → +7XXXXXXXXXX) — «голый» 10-значный номер без
        // префикса намеренно НЕ достраиваем: пользователь обязан ввести
        // +7 или 8 сам (см. validate() ниже), автодобавление молча
        // обошло бы это требование.
        if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true)) {
            return '+7'.substr($digits, 1);
        }

        return $value;
    }

    /**
     * Принимает любой российский код (не только мобильный 9XX), но
     * префикс +7/8/7 обязателен — «голый» 10-значный номер без него не
     * считается валидным.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Допустимые символы: цифры, пробелы, дефис, скобки, точка, плюс.
        // Отсекает мусор вроде '89873332234оо' раньше, чем preg_replace
        // молча его вырежет.
        if (! preg_match('/^[\d\s\-().+]+$/', $value)) {
            $fail('Поле :attribute может содержать только цифры, пробелы и символы + - ( ).');

            return;
        }

        // Плюс допустим только один и только в самом начале.
        if (substr_count($value, '+') > 1 || (str_contains($value, '+') && ! str_starts_with(trim($value), '+'))) {
            $fail('Поле :attribute может содержать только цифры, пробелы и символы + - ( ).');

            return;
        }

        $digits = preg_replace('/\D/', '', $value);

        // 10 цифр без ведущей 7/8 — распространённая ошибка (номер набрали
        // без кода страны), отдельное сообщение объясняет, чего не хватает.
        // '8987333223' (10 цифр, но начинается с 8) сюда не попадает —
        // это уже похоже на 11-значный номер с пропущенной цифрой, для
        // него ниже общее сообщение про 11 цифр.
        if (strlen($digits) === 10 && ! in_array($digits[0], ['7', '8'], true)) {
            $fail('Поле :attribute должно начинаться с +7 или 8. Пример: +7 (999) 123-45-67.');

            return;
        }

        $startsWithPlus = str_starts_with(trim($value), '+');

        // Итог: ровно 11 цифр, первая 7 или 8; если исходно был «+», после
        // него обязана идти 7 (+8... — не формат).
        if (strlen($digits) !== 11 || ! in_array($digits[0], ['7', '8'], true) || ($startsWithPlus && $digits[0] !== '7')) {
            $fail('Поле :attribute должно быть российским номером из 11 цифр. Пример: +7 (999) 123-45-67 или 8 999 123 45 67.');
        }
    }
}
