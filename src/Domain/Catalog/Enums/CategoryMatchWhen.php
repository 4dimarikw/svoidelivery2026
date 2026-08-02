<?php

namespace Domain\Catalog\Enums;

/**
 * Sub-branch of CategoryMatchType::Alcohol, checked by
 * CategorySlugResolver::resolveAlcoholicSlug() in this fixed order:
 * Advent → NoAbv → Style → DefaultRule. Meaningless for other match types.
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
