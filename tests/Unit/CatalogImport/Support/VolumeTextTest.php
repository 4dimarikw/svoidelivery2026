<?php

namespace Tests\Unit\CatalogImport\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use Services\CatalogImport\Support\VolumeText;
use Tests\TestCase;

class VolumeTextTest extends TestCase
{
    public static function parseProvider(): array
    {
        return [
            'коробка х не побеждает объём' => ['кор. 12х0,45л ж/б', 450, '0.45 л'],
            'литры конвертируются в мл' => ['пэт кег 20л', 20000, '20 л'],
            'объём в артикуле' => ['ФЕСТИВАЛЬНОЕ (ж/б 0,45л)', 450, '0.45 л'],
            'объём в наименовании с точкой' => ['ж/б 0,45л.', 450, '0.45 л'],
            'без объёма' => ['штучный товар', null, null],
        ];
    }

    #[DataProvider('parseProvider')]
    public function test_parse(string $text, ?int $expectedMl, ?string $expectedRaw): void
    {
        $result = VolumeText::parse($text);

        $this->assertSame($expectedMl, $result['ml']);
        $this->assertSame($expectedRaw, $result['raw']);
    }

    public static function stripProvider(): array
    {
        return [
            'скобки схлопываются' => ['ЙОКЕЛЬ (ж/б 0,45л)', 'ЙОКЕЛЬ (ж/б)'],
            'приклеенный /шт вырезается целиком' => ['ПШЕНИЧНОЕ (пэт кег 20л/шт)', 'ПШЕНИЧНОЕ (пэт кег)'],
            'висящая точка схлопывается' => ['Magic Mess "Томатос" (Gose) алк.5.5% /Мэджик Месс Гозе 7, ж/б 0,45л.', 'Magic Mess "Томатос" (Gose) алк.5.5% /Мэджик Месс Гозе 7, ж/б.'],
            'середина строки' => ['HopHead "Лимонад" (Lemonade)/ Клубника, ж/б 0,33л', 'HopHead "Лимонад" (Lemonade)/ Клубника, ж/б'],
            'без объёма — не тронуто' => ['штучный товар', 'штучный товар'],
            'приклеенный /кк' => ['кег кег 30л/кк', 'кег кег'],
        ];
    }

    #[DataProvider('stripProvider')]
    public function test_strip(string $text, string $expected): void
    {
        $this->assertSame($expected, VolumeText::strip($text));
    }

    public function test_strip_of_text_that_is_only_volume_returns_empty_string(): void
    {
        // Пустой результат — ответственность вызывающего кода (см.
        // PersistProductStage::stripOrFallback()), не самого VolumeText.
        $this->assertSame('', VolumeText::strip('0,45л'));
    }
}
