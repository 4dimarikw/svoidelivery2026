<?php

namespace Infrastructure\Settings;

use Domain\Catalog\Enums\ProductStatus;
use Spatie\LaravelSettings\Settings;

/**
 * Настройки импорта каталога 1С: маркеры категорий, обнуление остатков, статус товаров, период новинок, кеш и
 * время обновления.
 */
class CatalogImportSettings extends Settings
{
    public string $alcohol_marker = 'Алкогольная продукция';

    public string $accessory_marker = 'Сопутствующие товары';

    public string $advent_marker = 'адвент';

    /**
     * Slug резервной категории для строк, не сопоставленных с правилами.
     */
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

    /**
     * Параметр сохраняется в настройках; в текущем коде не читается.
     */
    public bool $cache = false;

    /**
     * Наценка сохраняется в настройках; в текущем коде не применяется.
     */
    public int $extra_charge = 0;

    public static function group(): string
    {
        return 'catalog_import';
    }
}
