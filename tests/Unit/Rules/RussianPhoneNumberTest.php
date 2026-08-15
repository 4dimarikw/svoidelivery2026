<?php

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use Support\Rules\RussianPhoneNumber;
use Tests\TestCase;

class RussianPhoneNumberTest extends TestCase
{
    private function fails(mixed $value): array
    {
        $failed = [];
        (new RussianPhoneNumber)->validate('phone', $value, function (string $message) use (&$failed) {
            $failed[] = $message;
        });

        return $failed;
    }

    public static function validProvider(): array
    {
        return [
            'плюс, скобки, дефисы' => ['+7 (987) 333-22-34'],
            'плюс без форматирования' => ['+79873332234'],
            '8, скобки, дефисы' => ['8 (495) 555-12-34'],
            '8, пробелы' => ['8 987 333 22 34'],
            '8, без пробелов' => ['89873332234'],
            '7 без плюса' => ['7 987 333 22 34'],
        ];
    }

    #[DataProvider('validProvider')]
    public function test_valid_numbers_pass(string $value): void
    {
        $this->assertSame([], $this->fails($value));
    }

    public static function invalidProvider(): array
    {
        return [
            '10 цифр без префикса' => ['9873332234'],
            '+8 не формат — после + может быть только 7' => ['+8 987 333 22 34'],
            'лишние символы' => ['89873332234оо'],
            'не хватает цифры' => ['8987333223'],
            'слишком много цифр' => ['898733322341'],
            'буквы вместо цифр' => ['abcdefghijk'],
            'два плюса' => ['++79873332234'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function test_invalid_numbers_fail(string $value): void
    {
        $this->assertNotSame([], $this->fails($value));
    }

    public static function normalizeProvider(): array
    {
        return [
            ['8 (987) 333-22-34', '+79873332234'],
            ['+7 987 333 22 34', '+79873332234'],
            ['7 987 333 22 34', '+79873332234'],
            // 10 цифр без префикса — не достраиваем +7 сами, пользователь
            // обязан ввести код страны; иначе тихо обошли бы требование.
            ['9873332234', '9873332234'],
            // мусор нормализация тоже не трогает — правило само покажет
            // ошибку, а old() вернёт в поле то, что человек ввёл.
            ['89873332234оо', '89873332234оо'],
        ];
    }

    #[DataProvider('normalizeProvider')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, RussianPhoneNumber::normalize($input));
    }
}
