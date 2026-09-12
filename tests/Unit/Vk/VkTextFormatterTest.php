<?php

declare(strict_types=1);

namespace Tests\Unit\Vk;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Services\Vk\Support\VkTextFormatter;

class VkTextFormatterTest extends TestCase
{
    public static function links(): array
    {
        return [
            ['[https://vk.ru/topic-194587991_46740865|в этом обсуждении]', '<a href="https://vk.ru/topic-194587991_46740865">в этом обсуждении</a>'],
            ['См. [http://example.test/?a=1&b=2|"Квас" & <пиво>]!', 'См. <a href="http://example.test/?a=1&amp;b=2">&quot;Квас&quot; &amp; &lt;пиво&gt;</a>!'],
            ['[https://example.test/a|Один] и [https://example.test/b|Два]', '<a href="https://example.test/a">Один</a> и <a href="https://example.test/b">Два</a>'],
        ];
    }

    #[DataProvider('links')]
    public function test_import_produces_safe_links(string $source, string $expected): void
    {
        $formatter = new VkTextFormatter;
        $this->assertSame($expected, $formatter->forImport($source));
        $this->assertSame($expected, $formatter->forImport($expected));
    }

    public static function invalidLinks(): array
    {
        return array_map(fn ($text) => [$text], [
            '[javascript:alert(1)|Нажать]', '[data:text/html,test|Нажать]', '[ftp://example.test/a|Файл]',
            '[https://|Ссылка]', '[https://example.test missing|Ссылка]', '[https://example.test|Не закрыта',
            '<a href="javascript:alert(1)">Нажать</a>',
            '<a href="https://example.test" onclick="alert(1)">Нажать</a>',
            '<b>Текст</b> & <script>alert(1)</script>',
        ]);
    }

    #[DataProvider('invalidLinks')]
    public function test_other_constructs_remain_literal_text(string $source): void
    {
        $formatter = new VkTextFormatter;
        $this->assertSame($source, $formatter->forImport($source));
    }

    public function test_import_keeps_mentions_and_existing_html_unchanged(): void
    {
        $formatter = new VkTextFormatter;
        $text = '[id123|Имя & Фамилия] [club123|Группа] <b>Текст</b>';
        $this->assertSame($text, $formatter->forImport($text));
    }
}
