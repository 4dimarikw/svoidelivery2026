<?php

namespace Services\CatalogImport;

use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;

/**
 * Per-import cache of read-only reference data (categories, containers).
 * Loaded once before the row loop to eliminate N+1 queries in Resolve* stages.
 * Invariant: both tables are small reference data (< ~1000 rows total) — fits comfortably in RAM.
 */
class LookupCache
{
    /** @var array<string, Category> */
    private array $categories = [];

    /** @var array<string, Container> */
    private array $containers = [];

    public function __construct()
    {
        foreach (Category::all() as $category) {
            $this->categories[$category->slug] = $category;
        }

        foreach (Container::all() as $container) {
            $this->containers[$container->code] = $container;
        }
    }

    public function findCategoryBySlug(string $slug): ?Category
    {
        return $this->categories[$slug] ?? null;
    }

    public function findContainerByCode(string $code): ?Container
    {
        return $this->containers[$code] ?? null;
    }
}
