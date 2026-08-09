<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Volume;
use Illuminate\Support\Facades\Cache;

/**
 * Кешированные значения справочных фильтров каталога (categories, beer_styles,
 * manufacturers, volumes, containers) — раньше каждый из пяти AbstractFilter::values()
 * бил в БД напрямую при каждом рендере страницы каталога (5 SELECT на запрос).
 * Справочники меняются только из MoonShine-админки и при импорте — редко,
 * поэтому кешируем целиком, по образцу Services\CatalogImport\CategoryRegistry.
 *
 * Один общий CACHE_KEY на все пять справочников, а не пять отдельных ключей —
 * при CACHE_STORE=database (или любом другом сторе без Cache::many()) отдельные
 * ключи означали бы пять запросов к кешу вместо пяти запросов к самим таблицам,
 * то есть без выигрыша. Плюс мемоизация на HTTP-запрос через контейнер
 * (singleton) — items() зовёт values() для нескольких фильтров, и без неё
 * кеш читался бы 5 раз даже при hit.
 *
 * Инвалидация — через booted()-хуки на Category/BeerStyle/Manufacturer/Volume/
 * Container (saved/deleted). Массовые правки через query builder
 * (Container::query()->update(...)) эти хуки не поднимают — в таком случае
 * нужен явный flush() или `php artisan cache:clear`.
 *
 * Состояние хранится в контейнере (singleton, см. AppServiceProvider), не в
 * голой static-переменной — по тому же образцу, что и CategoryRegistry:
 * flush() дёргает app()->forgetInstance(), а не просто обнуляет свойство,
 * так что следующий app(self::class) гарантированно строит новый инстанс
 * с чтением Cache::rememberForever() заново, а не держит устаревшую копию.
 */
final class FilterOptionsRegistry
{
    public const string CACHE_KEY = 'catalog.filter_options';

    /** @var array<string, array<int, string>> */
    private readonly array $options;

    public function __construct()
    {
        $this->options = Cache::rememberForever(self::CACHE_KEY, static fn () => self::load());
    }

    /**
     * @return array<int, string>
     */
    public static function for(string $key): array
    {
        return app(self::class)->options[$key] ?? [];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        app()->forgetInstance(self::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function load(): array
    {
        return [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'beer_styles' => BeerStyle::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'manufacturers' => Manufacturer::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            // У Volume нет is_active, в отличие от остальных справочников.
            'volumes' => Volume::query()->orderBy('milliliters')->pluck('label', 'id')->all(),
            'containers' => Container::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'label', 'name'])
                ->mapWithKeys(fn (Container $container) => [$container->id => $container->label ?: $container->name])
                ->all(),
        ];
    }
}
