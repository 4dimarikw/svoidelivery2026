<?php

namespace Domain\Catalog\Enums;

/**
 * Условие правила Alcohol. Порядок проверки в resolveAlcoholicSlug(): Advent → NoAbv → Style → DefaultRule. Для
 * других типов не применяется.
 */
enum CategoryMatchWhen: string
{
    case Advent = 'advent';
    case NoAbv = 'no_abv';
    case Style = 'style';
    case DefaultRule = 'default';

    public function toString(): string
    {
        return match ($this) {
            self::Advent => 'Адвент-календарь',
            self::NoAbv => 'Без ABV',
            self::Style => 'По стилю (keyword)',
            self::DefaultRule => 'По умолчанию',
        };
    }
}
