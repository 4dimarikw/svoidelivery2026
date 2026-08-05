<?php

namespace Tests\Unit\Support;

use Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_ui_id_is_deterministic(): void
    {
        $this->assertSame(ui_id('email'), ui_id('email'));
    }

    public function test_ui_id_slugifies_the_name(): void
    {
        // Str::slug() отбрасывает "[]"/"." целиком (не трактует их как разделитель).
        $this->assertSame('f-address0city', ui_id('address[0][city]'));
        // Пробел и "_" — реальные разделители слов для Str::slug().
        $this->assertSame('f-user-name', ui_id('user_name'));
        $this->assertSame('f-user-name', ui_id('User Name'));
    }

    public function test_normalize_name_collapses_whitespace_and_case(): void
    {
        $this->assertSame('балтика', normalize_name('  БАЛТИКА  '));
        $this->assertSame('пивной дом', normalize_name('Пивной    Дом'));
    }

    public function test_normalize_name_is_the_firstorcreate_matching_key(): void
    {
        // Импорт делает firstOrCreate по normalized_name — разное написание
        // одного и того же бренда должно давать одинаковый ключ.
        $this->assertSame(
            normalize_name('Балтика'),
            normalize_name('  балтика '),
        );
    }
}
