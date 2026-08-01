<?php

namespace Services\CatalogImport;

use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\ContainerType;
use Domain\Catalog\Models\PackagingType;

/**
 * Per-import cache of read-only reference data (categories, container types).
 * Loaded once before the row loop to eliminate N+1 queries in Resolve* stages.
 * Invariant: both tables are small reference data (< ~1000 rows total) — fits comfortably in RAM.
 * If an HTTP upload endpoint is added, ensure caller validates the upload path against
 * an allowed-directory whitelist before passing to CsvReader.
 */
class LookupCache
{
    /** @var array<string, Category> */
    private array $categories = [];

    /** @var array<string, PackagingType> */
    private array $containers = [];

    public function __construct()
    {
        foreach (Category::all() as $category) {
            $this->categories[$category->slug] = $category;
        }

        foreach (PackagingType::all() as $containerType) {
            $this->containers[$containerType->code] = $containerType;
        }
    }

    public function findCategoryBySlug(string $slug): ?Category
    {
        return $this->categories[$slug] ?? null;
    }

    public function findContainerByCode(string $code): ?PackagingType
    {
        return $this->containers[$code] ?? null;
    }
}
