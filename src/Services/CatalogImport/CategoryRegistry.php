<?php

namespace Services\CatalogImport;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\CategoryMatchRule;
use Illuminate\Support\Facades\Cache;
use Infrastructure\Settings\CatalogImportSettings;

/**
 * Единая точка чтения реестра категорий из categories, category_match_rules и CatalogImportSettings. Этапы
 * импорта получают типизированные данные через этот класс. Общий кеш исключает запросы к базе для каждой строки
 * CSV; его очищают события Category и CategoryMatchRule и сохранение настроек импорта.
 */
final class CategoryRegistry
{
    public const CACHE_KEY = 'catalog.category_registry';

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
     * Плоский список правил в общем порядке priority, id. Хранится отдельно от категорий, чтобы rulesOfType()
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
        $this->categories = $cached['categories'];
        $this->orderedRules = $cached['rules'];

        $this->resolution = [
            'alcohol_marker' => $settings->alcohol_marker,
            'accessory_marker' => $settings->accessory_marker,
            'advent_marker' => $settings->advent_marker,
            'fallback' => $settings->fallback_slug,
        ];
    }

    /**
     * @return array{categories: array<string, array<string, mixed>>, rules: list<array{slug: string, rule: array<string, mixed>}>}
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

        $orderedRules = [];

        $rules = CategoryMatchRule::query()
            ->active()
            ->with('category:id,slug')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $slug = $rule->category->slug;

            if (! isset($categories[$slug])) {
                continue;
            }

            // Пустое значение accessory_title или contains совпало бы с любым названием. Проверка нужна и при
            // использовании реестра вне команды catalog:validate-registry.
            if ($rule->type !== CategoryMatchType::Alcohol && blank($rule->value)) {
                continue;
            }

            $orderedRules[] = ['slug' => $slug, 'rule' => match ($rule->type) {
                CategoryMatchType::Alcohol => array_filter([
                    'type' => 'alcohol',
                    'when' => $rule->match_when?->value,
                    'keyword' => $rule->match_when === CategoryMatchWhen::Style ? $rule->value : null,
                ], static fn ($v) => $v !== null),
                CategoryMatchType::Contains => ['type' => 'contains', 'needle' => $rule->value],
                // Каждая запись содержит одно ключевое слово. resolveAccessoryTitleSlug() объединяет слова
                // подходящих правил в общую карту категорий.
                CategoryMatchType::AccessoryTitle => ['type' => 'accessory_title', 'keywords' => [$rule->value]],
            }];
        }

        return ['categories' => $categories, 'rules' => $orderedRules];
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
     * Правила заданного type в порядке priority, затем id.
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
