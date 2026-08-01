<?php

namespace Services\CatalogImport;

/**
 * Единая точка чтения `config('catalog_import.categories')` +
 * `config('catalog_import.category_resolution')`. Стейджи (Normalize, Container,
 * Volume, Brand, ProductIdentity, CategorySlugResolver) больше не читают конфиг
 * напрямую по разрозненным ключам — конфиг сам является реестром категорий,
 * этот класс лишь даёт типизированный доступ к его записям.
 */
final class CategoryRegistry
{
    /** @var array<string, array<string, mixed>> */
    private readonly array $categories;

    /** @var array<string, string> */
    private readonly array $resolution;

    public function __construct()
    {
        $this->categories = config('catalog_import.categories', []);
        $this->resolution = config('catalog_import.category_resolution', []);
    }

    public function property(string $slug, string $key, mixed $default = null): mixed
    {
        return $this->categories[$slug][$key] ?? $default;
    }

    public function expectsContainer(string $slug): bool
    {
        return (bool) $this->property($slug, 'container', false);
    }

    public function expectsVolume(string $slug): bool
    {
        return (bool) $this->property($slug, 'volume', false);
    }

    public function isPriceExempt(string $slug): bool
    {
        return (bool) $this->property($slug, 'price_exempt', false);
    }

    public function defaultBrand(string $slug): ?string
    {
        return $this->property($slug, 'default_brand');
    }

    public function nameFromArticle(string $slug): bool
    {
        return (bool) $this->property($slug, 'name_from_article', false);
    }

    public function containerCode(string $slug): ?string
    {
        return $this->property($slug, 'container_code');
    }

    public function alcoholMarker(): string
    {
        return $this->resolution['alcohol_marker'] ?? 'Алкогольная продукция';
    }

    public function accessoryMarker(): string
    {
        return $this->resolution['accessory_marker'] ?? 'Сопутствующие товары';
    }

    public function adventMarker(): string
    {
        return $this->resolution['advent_marker'] ?? 'адвент';
    }

    public function fallback(): string
    {
        return $this->resolution['fallback'] ?? 'not-defined';
    }

    /**
     * Все категории, чьи match[] содержат правило заданного type, в порядке
     * объявления в конфиге (порядок определяет приоритет проверки).
     *
     * @return list<array{slug: string, rule: array<string, mixed>}>
     */
    public function rulesOfType(string $type): array
    {
        $result = [];

        foreach ($this->categories as $slug => $config) {
            foreach ($config['match'] ?? [] as $rule) {
                if (($rule['type'] ?? null) === $type) {
                    $result[] = ['slug' => $slug, 'rule' => $rule];
                }
            }
        }

        return $result;
    }
}
