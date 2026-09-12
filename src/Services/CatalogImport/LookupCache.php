<?php

namespace Services\CatalogImport;

use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;

/**
 * Кеш справочников категорий и тары на один импорт. Загружается до обработки строк, исключая повторные запросы в
 * этапах Resolve*. Рассчитан на небольшие справочники, помещающиеся в память.
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
