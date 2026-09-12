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
 * Кеш справочных фильтров categories, beer_styles, manufacturers, volumes и containers. Общий CACHE_KEY и
 * singleton ограничивают число чтений кеша. События saved/deleted моделей очищают кеш; массовые изменения через
 * построитель запросов требуют явного flush(). Метод flush() также сбрасывает экземпляр в контейнере, чтобы
 * последующее обращение загрузило актуальные данные.
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
