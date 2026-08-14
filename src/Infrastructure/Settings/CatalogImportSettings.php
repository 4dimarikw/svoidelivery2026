<?php

namespace Infrastructure\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Global markers used by CategorySlugResolver, formerly the flat
 * `category_resolution` array in config/catalog_import.php — see
 * CategoryRegistry, which is the only reader of this class.
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

    public static function group(): string
    {
        return 'catalog_import';
    }
}
