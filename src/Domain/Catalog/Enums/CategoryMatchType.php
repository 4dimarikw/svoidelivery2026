<?php

namespace Domain\Catalog\Enums;

/**
 * Rule type interpreted by Services\CatalogImport\CategorySlugResolver, in
 * fixed priority order: Alcohol → Contains → AccessoryTitle → fallback.
 * See CategorySlugResolver::resolve() for the branch logic each type feeds.
 */
enum CategoryMatchType: string
{
    case Alcohol = 'alcohol';
    case Contains = 'contains';
    case AccessoryTitle = 'accessory_title';

    public function toString(): string
    {
        return match ($this) {
            self::Alcohol => 'Алкоголь (верхний сегмент)',
            self::Contains => 'Подстрока в категории',
            self::AccessoryTitle => 'Ключевое слово в наименовании',
        };
    }
}
