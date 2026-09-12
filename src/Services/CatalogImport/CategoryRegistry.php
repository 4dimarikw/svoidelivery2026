<?php

namespace Services\CatalogImport;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use Domain\Catalog\Models\Category;
use Illuminate\Support\Facades\Cache;
use Infrastructure\Settings\CatalogImportSettings;

/**
 * Единая точка чтения реестра категорий из categories, category_matching.php и CatalogImportSettings. Этапы
 * импорта получают типизированные данные через этот класс. Кеш категорий исключает запросы к базе для каждой строки
 * CSV; его очищают события Category и сохранение настроек импорта.
 */
final class CategoryRegistry
{
    public const CACHE_KEY = 'catalog.category_registry.categories.v2';

    /**
     * Очищает запись кеша и singleton в контейнере. Сброс обоих уровней гарантирует загрузку актуального реестра
     * при следующем обращении.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        app()->forgetInstance(self::class);
    }

    /** @var array<string, array<string, mixed>> */
    private readonly array $categories;

    /**
     * Плоский список правил по priority и порядку списка. Хранится отдельно от категорий, чтобы rulesOfType()
     * сохранял межкатегорийный порядок правил.
     *
     * @var list<array{slug: string, rule: array<string, mixed>}>
     */
    private readonly array $orderedRules;

    /** @var array<string, string> */
    private readonly array $resolution;

    public function __construct(?CatalogImportSettings $settings = null)
    {
        $settings ??= app(CatalogImportSettings::class);

        $cached = Cache::rememberForever(self::CACHE_KEY, static fn () => self::loadCategories());
        $this->categories = $cached;
        $this->orderedRules = $this->loadRules();

        $this->resolution = [
            'alcohol_marker' => $settings->alcohol_marker,
            'accessory_marker' => $settings->accessory_marker,
            'advent_marker' => $settings->advent_marker,
            'fallback' => $settings->fallback_slug,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function loadCategories(): array
    {
        $categories = [];

        foreach (Category::query()->get() as $category) {
            $categories[$category->slug] = [
                'name' => $category->name,
                'container' => $category->expects_container,
                'volume' => $category->expects_volume,
                'price_exempt' => $category->price_exempt,
                'default_brand' => $category->default_brand,
                'name_from_article' => $category->name_from_article,
                'container_code' => $category->container_code,
            ];
        }

        return $categories;
    }

    private function loadRules(): array
    {
        $orderedRules = [];
        foreach (CategoryMatchingRules::ordered(config('category_matching.rules')) as $rule) {
            if (! $rule['is_active'] || ! isset($this->categories[$rule['slug']])) {
                continue;
            }
            $orderedRules[] = ['slug' => $rule['slug'], 'rule' => match ($rule['type']) {
                CategoryMatchType::Alcohol->value => array_filter([
                    'type' => 'alcohol',
                    'when' => $rule['match_when'],
                    'keyword' => $rule['match_when'] === CategoryMatchWhen::Style->value ? $rule['value'] : null,
                ], static fn ($value) => $value !== null),
                CategoryMatchType::Contains->value => ['type' => 'contains', 'needle' => $rule['value']],
                CategoryMatchType::AccessoryTitle->value => ['type' => 'accessory_title', 'keywords' => [$rule['value']]],
            }];
        }

        return $orderedRules;
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
     * Правила заданного type в порядке priority, затем порядку списка.
     *
     * @return list<array{slug: string, rule: array<string, mixed>}>
     */
    public function rulesOfType(string $type): array
    {
        return array_values(array_filter(
            $this->orderedRules,
            static fn (array $entry): bool => ($entry['rule']['type'] ?? null) === $type,
        ));
    }
}
