<?php

namespace Infrastructure\Settings;

use Domain\Catalog\Enums\ProductStatus;
use Spatie\LaravelSettings\Settings;

/**
 * Настройки импорта каталога 1С. Изначально — только глобальные маркеры для
 * CategorySlugResolver (см. CategoryRegistry), но со временем сюда же
 * переехали настройки обнуления остатков (zero_out_*) и часть настроек,
 * ранее живших в GeneralSettings (product_status, new_days, cache,
 * last_catalog_update) — они относятся к каталогу/импорту, а не к
 * «общим» настройкам сайта.
 */
class CatalogImportSettings extends Settings
{
    public string $alcohol_marker = 'Алкогольная продукция';

    public string $accessory_marker = 'Сопутствующие товары';

    public string $advent_marker = 'адвент';

    /** Slug of the category new/unmatched rows fall back to. */
    public string $fallback_slug = 'not-defined';

    /**
     * Обнулять stock_quantity/in_stock у товаров, отсутствующих в последнем
     * CSV — см. Domain\Catalog\Actions\ZeroOutStaleProductsAction. Выключатель
     * на случай, если магазин временно не хочет такого поведения (например,
     * при заведомо неполных тестовых выгрузках 1С).
     */
    public bool $zero_out_missing = true;

    /**
     * Предохранитель: если товаров, отсутствующих в CSV, оказывается больше
     * этого процента от текущего числа товаров в наличии — обнуление
     * отменяется целиком (типичный симптом битого/обрезанного файла от 1С).
     */
    public int $zero_out_max_percent = 20;

    /** Статус, с которым создаются новые товары при первом импорте. */
    public string $product_status = ProductStatus::DRAFT->value;

    /** Число дней, в течение которых товар считается «новинкой» (Product::isNew()). */
    public int $new_days = 7;

    /** Дата последнего успешного импорта каталога (строка, не читается кодом). */
    public ?string $last_catalog_update = null;

    /** Не используется ни одним читателем кода — перенесено из GeneralSettings как есть. */
    public bool $cache = false;

    /** Наценка. Не используется ни одним читателем кода — перенесено из GeneralSettings как есть. */
    public int $extra_charge = 0;

    public static function group(): string
    {
        return 'catalog_import';
    }
}
