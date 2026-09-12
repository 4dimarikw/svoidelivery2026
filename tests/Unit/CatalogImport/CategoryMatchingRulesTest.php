<?php

namespace Tests\Unit\CatalogImport;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Services\CatalogImport\CategoryMatchingRules;

class CategoryMatchingRulesTest extends TestCase
{
    private static function rule(array $changes = []): array
    {
        return $changes + ['slug' => 'beer', 'type' => 'contains', 'match_when' => null,
            'value' => 'пиво', 'priority' => 10, 'is_active' => true];
    }

    public function test_equal_priorities_preserve_list_order_and_disabled_rules(): void
    {
        $a = self::rule(['slug' => 'a', 'priority' => 20]);
        $b = self::rule(['slug' => 'b', 'is_active' => false]);
        $c = self::rule(['slug' => 'c']);
        $this->assertSame([$b, $c, $a], CategoryMatchingRules::ordered([$a, $b, $c]));
    }

    #[DataProvider('invalidRules')]
    public function test_invalid_configuration_is_rejected(mixed $rules): void
    {
        $this->expectException(InvalidArgumentException::class);
        CategoryMatchingRules::ordered($rules);
    }

    public static function invalidRules(): array
    {
        return [
            [null], ['invalid'], [['named' => self::rule()]], [[[]]],
            [[self::rule(['type' => 'unknown'])]],
            [[self::rule(['match_when' => 'style'])]],
            [[self::rule(['value' => ' '])]],
            [[self::rule(['priority' => -1])]],
            [[self::rule(['priority' => '10'])]],
            [[self::rule(['is_active' => 1])]],
            [[self::rule(['type' => 'alcohol', 'match_when' => null])]],
            [[self::rule(['type' => 'alcohol', 'match_when' => 'default'])]],
            [[self::rule(['type' => 'accessory_title', 'value' => 'ТАРА']),
                self::rule(['type' => 'accessory_title', 'value' => 'тара'])]],
        ];
    }
}
