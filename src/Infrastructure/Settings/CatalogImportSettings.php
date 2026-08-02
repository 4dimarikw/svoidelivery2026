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

    public static function group(): string
    {
        return 'catalog_import';
    }
}
