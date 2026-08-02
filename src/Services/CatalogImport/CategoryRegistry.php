<?php

namespace Services\CatalogImport;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\CategoryMatchRule;
use Illuminate\Support\Facades\Cache;
use Infrastructure\Settings\CatalogImportSettings;

/**
 * Единая точка чтения реестра категорий. Раньше это были
 * `config('catalog_import.categories')` + `config('catalog_import.category_resolution')`;
 * теперь источник — таблицы `categories`/`category_match_rules` (флаги на
 * Category, правила в CategoryMatchRule) и `Infrastructure\Settings\CatalogImportSettings`
 * (глобальные маркеры сегментов, редактируются в MoonShine). Стейджи (Normalize,
 * Container, Volume, Brand, ProductIdentity, CategorySlugResolver) не читают
 * ни БД, ни settings напрямую — этот класс даёт им тот же типизированный
 * доступ, что и раньше, поменялось только наполнение.
 *
 * Результат кэшируется целиком (`CACHE_KEY`): catalog:import прогоняет
 * резолвер на каждой строке CSV (тысячи за запуск), поэтому запрос в БД на
 * строку недопустим. Кэш инвалидируется `Category`/`CategoryMatchRule::booted()`
 * и вручную при сохранении CatalogImportSettings (см. CatalogImportSettingsPage).
 */
final class CategoryRegistry
{
    public const CACHE_KEY = 'catalog.category_registry';

    /**
     * Single invalidation entry point — clears both the cache entry AND the
     * resolved container instance. CategoryRegistry is bound as a singleton
     * (see AppServiceProvider) so catalog:import doesn't re-run the cache
     * lookup on every CSV row; a bare Cache::forget() alone would leave that
     * singleton holding a stale readonly copy for the rest of the process.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        app()->forgetInstance(self::class);
    }

    /** @var array<string, array<string, mixed>> */
    private readonly array $categories;

    /**
     * Flat rule list in the SAME global priority order they were queried in
     * (ORDER BY priority, id) — kept separate from $categories precisely so
     * rulesOfType() doesn't have to reconstruct cross-category order from
     * per-category grouping, which would silently fall back to category
     * insertion (id) order instead of the admin-edited `priority` column.
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

            // A blank value on a non-alcohol rule would otherwise compile
            // into a match-everything branch downstream (accessory_title →
            // empty keyword → empty regex alternative matches any title;
            // contains → empty needle → str_contains(..., '') is always
            // true). catalog:validate-registry flags this in the normal
            // import path, but CategoryRegistry is constructible outside
            // it, so guard here too.
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
                // CategorySlugResolver::resolveAccessoryTitleSlug() iterates
                // rule['keywords'] ?? [] and flattens across all matching
                // rules into one map regardless of grouping, so one DB row
                // per keyword (vs. the old config's keywords-array-per-rule)
                // produces an identical result.
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
     * Все правила заданного type, в порядке `priority` (и `id` как
     * тай-брейкер) — раньше порядок определялся объявлением в конфиге,
     * теперь явной колонкой `category_match_rules.priority`.
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
