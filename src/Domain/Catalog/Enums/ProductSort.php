<?php

namespace Domain\Catalog\Enums;

/**
 * Ключи сортировки каталога (SortFilter, ProductBuilder::sorted()). Подписи
 * хардкодом, без lang-файла — так же, как у ProductStatus::toString().
 *
 * Порядок кейсов = порядок опций в <select> (ProductSort::cases()). Дата
 * добавления — единственный критерий без пары ASC/DESC ("сначала старые"
 * никому не нужно), у остальных — обе стороны.
 */
enum ProductSort: string
{
    case CREATED_DESC = 'created_desc';
    case BRAND_ASC = 'brand_asc';
    case BRAND_DESC = 'brand_desc';
    case MANUFACTURER_ASC = 'manufacturer_asc';
    case MANUFACTURER_DESC = 'manufacturer_desc';
    case RATING_DESC = 'rating_desc';
    case RATING_ASC = 'rating_asc';
    case STYLE_ASC = 'style_asc';
    case STYLE_DESC = 'style_desc';
    case PRICE_ASC = 'price_asc';
    case PRICE_DESC = 'price_desc';

    public function label(): string
    {
        return match ($this) {
            self::CREATED_DESC => 'Сначала новые',
            self::BRAND_ASC => 'Название: А→Я',
            self::BRAND_DESC => 'Название: Я→А',
            self::MANUFACTURER_ASC => 'Производитель: А→Я',
            self::MANUFACTURER_DESC => 'Производитель: Я→А',
            self::RATING_DESC => 'Рейтинг Untappd: сначала высокий',
            self::RATING_ASC => 'Рейтинг Untappd: сначала низкий',
            self::STYLE_ASC => 'Стиль: А→Я',
            self::STYLE_DESC => 'Стиль: Я→А',
            self::PRICE_ASC => 'Цена: сначала дешёвые',
            self::PRICE_DESC => 'Цена: сначала дорогие',
        };
    }

    public static function default(): self
    {
        return self::CREATED_DESC;
    }
}
