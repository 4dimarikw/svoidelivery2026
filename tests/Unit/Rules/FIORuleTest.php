<?php

namespace Tests\Unit\Rules;

use Infrastructure\Rules\FIORule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FIORuleTest extends TestCase
{
    private function fails(mixed $value): array
    {
        $failed = [];
        (new FIORule)->validate('name', $value, function (string $message) use (&$failed) {
            $failed[] = $message;
        });

        return $failed;
    }

    public function test_valid_name_passes(): void
    {
        $this->assertSame([], $this->fails('Иванов Иван'));
    }

    public static function invalidProvider(): array
    {
        return [
            'пусто' => [''],
            'не строка' => [123],
            'короче двух символов' => ['И'],
            'длиннее 50 символов' => [str_repeat('а', 51)],
            'латиница' => ['Ivanov'],
            'цифры' => ['Иванов1'],
            'только пробелы и дефисы' => ['- -'],
            'двойной пробел' => ['Иванов  Иван'],
            'начинается не с буквы' => ['-Иванов'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function test_invalid_values_fail(mixed $value): void
    {
        $this->assertNotSame([], $this->fails($value));
    }

    public function test_hyphenated_surname_is_allowed(): void
    {
        $this->assertSame([], $this->fails('Петрова-Иванова Мария'));
    }
}
