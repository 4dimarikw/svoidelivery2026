<?php

namespace Domain\Catalog\Enums;

/**
 * Тип правила CategorySlugResolver. Порядок проверки: Alcohol → Contains → AccessoryTitle → резервная категория.
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
