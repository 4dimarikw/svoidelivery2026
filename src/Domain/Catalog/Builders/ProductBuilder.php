<?php

declare(strict_types=1);

namespace Domain\Catalog\Builders;

use Domain\Catalog\Enums\ProductSort;
use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\BeerProductDetail;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Кастомный query builder Product — вынесен из Product::scope*() через
 * newEloquentBuilder(), чтобы модель не разрасталась query-логикой. Методы
 * без префикса scope*: кастомному билдеру он не нужен, они и так chainable
 * и на инстансе, и статически через Model::__callStatic.
 *
 * @extends Builder<Product>
 */
class ProductBuilder extends Builder
{
    public function active(): static
    {
        return $this->where('status', ProductStatus::PUBLISHED)->where('in_stock', true);
    }

    /**
     * Опубликованные товары для витрины. Отдельно от active() — там status
     * и in_stock склеены в одно условие, а для каталога "в наличии" должен
     * быть самостоятельным фильтром, а не частью базовой выборки.
     */
    public function published(): static
    {
        return $this->where('status', ProductStatus::PUBLISHED);
    }

    /**
     * category_id на Product обязателен, поэтому проверка активности безусловна —
     * товар деактивированной категории не должен попадать в выдачу вообще, вне
     * зависимости от того, выбран ли этот фильтр явно. Работает и без фильтра:
     * CategoryFilter::apply() вызывается на каждом запросе каталога (FilterManager
     * прогоняет все зарегистрированные фильтры всегда), просто с пустым $categoryIds.
     */
    public function inCategories(array $categoryIds): static
    {
        return $this
            ->whereHas('category', fn (Builder $q) => $q->where('is_active', true))
            ->when($categoryIds !== [], fn (self $q) => $q->whereIn('category_id', $categoryIds));
    }

    /**
     * manufacturer_id nullable — товар без производителя не должен пропадать,
     * поэтому "неактивный производитель" проверяется только когда он вообще
     * указан. Безусловность — см. комментарий к inCategories().
     */
    public function ofManufacturers(array $manufacturerIds): static
    {
        return $this
            ->where(function (Builder $q) {
                $q->whereNull('manufacturer_id')
                    ->orWhereHas('manufacturer', fn (Builder $q2) => $q2->where('is_active', true));
            })
            ->when($manufacturerIds !== [], fn (self $q) => $q->whereIn('manufacturer_id', $manufacturerIds));
    }

    public function ofVolumes(array $volumeIds): static
    {
        return $this->when($volumeIds !== [], fn (self $q) => $q->whereIn('volume_id', $volumeIds));
    }

    public function ofContainers(array $containerIds): static
    {
        return $this->when($containerIds !== [], fn (self $q) => $q->whereIn('container_id', $containerIds));
    }

    public function inPriceRange(?string $min, ?string $max): static
    {
        return $this
            ->when($min !== null, fn (self $q) => $q->where('price', '>=', $min))
            ->when($max !== null, fn (self $q) => $q->where('price', '<=', $max));
    }

    public function search(?string $term): static
    {
        return $this->when($term !== null && $term !== '', fn (self $q) => $q->where('name', 'like', '%'.$term.'%'));
    }

    public function onlyInStock(bool $onlyInStock): static
    {
        return $this->when($onlyInStock, fn (self $q) => $q->where('in_stock', true));
    }

    /**
     * beer_style_id живёт на beerDetails (1:1, не у каждого товара она вообще
     * есть), не на products — whereHas(), не whereIn(). Guard оборачивает
     * ВЕСЬ whereHas(), а не условие внутри него: BeerStyleFilter::apply()
     * вызывается на каждом запросе каталога (FilterManager прогоняет все
     * фильтры всегда, просто с пустым $beerStyleIds) — без внешнего when()
     * сам факт регистрации фильтра отсекал бы из каталога любой товар без
     * beerDetails (аксессуары и т.п.), даже когда стиль никто не выбирал.
     */
    public function ofBeerStyles(array $beerStyleIds): static
    {
        return $this->when(
            $beerStyleIds !== [],
            fn (self $q) => $q->whereHas('beerDetails', fn (Builder $q2) => $q2->whereIn('beer_style_id', $beerStyleIds))
        );
    }

    /**
     * Тот же guard-принцип, что у ofBeerStyles() — см. комментарий там.
     */
    public function withAbvBetween(?string $min, ?string $max): static
    {
        return $this->when(
            $min !== null || $max !== null,
            fn (self $q) => $q->whereHas('beerDetails', function (Builder $q2) use ($min, $max) {
                $q2->when($min !== null, fn (Builder $q3) => $q3->where('abv', '>=', $min))
                    ->when($max !== null, fn (Builder $q3) => $q3->where('abv', '<=', $max));
            })
        );
    }

    /**
     * Тот же guard-принцип, что у ofBeerStyles() — см. комментарий там.
     */
    public function withIbuBetween(?string $min, ?string $max): static
    {
        return $this->when(
            $min !== null || $max !== null,
            fn (self $q) => $q->whereHas('beerDetails', function (Builder $q2) use ($min, $max) {
                $q2->when($min !== null, fn (Builder $q3) => $q3->where('ibu', '>=', $min))
                    ->when($max !== null, fn (Builder $q3) => $q3->where('ibu', '<=', $max));
            })
        );
    }

    /**
     * Сортировка каталога (SortFilter). Производитель/стиль/рейтинг — через
     * коррелированные подзапросы в ORDER BY, НЕ leftJoin: manufacturers.name
     * и beer_styles.name совпадают по имени с products.name, а search()
     * выше делает where('name', ...) без квалификации таблицы — leftJoin на
     * любую из них сделал бы 'name' неоднозначным для MySQL при одновременном
     * поиске + этой сортировкой. Подзапрос — изолированная область видимости
     * колонок, наружу ничего не протекает.
     *
     * Везде вторичный orderByDesc('id') — тай-брейк для строк с одинаковым
     * значением сортировки, иначе порядок "плавает" между запросами
     * пагинации. NULL (нет производителя/стиля/рейтинга): MySQL кладёт их в
     * начало при ASC и в конец при DESC — принято как есть для обеих сторон
     * пары, без NULLS LAST-трюков (MySQL их не умеет нативно).
     */
    public function sorted(ProductSort $sort): static
    {
        return match ($sort) {
            ProductSort::CREATED_DESC => $this->orderByDesc('id'),
            ProductSort::BRAND_ASC => $this->orderBy('brand')->orderByDesc('id'),
            ProductSort::BRAND_DESC => $this->orderByDesc('brand')->orderByDesc('id'),
            ProductSort::PRICE_ASC => $this->orderBy('price')->orderByDesc('id'),
            ProductSort::PRICE_DESC => $this->orderByDesc('price')->orderByDesc('id'),
            ProductSort::MANUFACTURER_ASC => $this->orderBy($this->manufacturerNameSubquery())->orderByDesc('id'),
            ProductSort::MANUFACTURER_DESC => $this->orderByDesc($this->manufacturerNameSubquery())->orderByDesc('id'),
            ProductSort::STYLE_ASC => $this->orderBy($this->beerStyleNameSubquery())->orderByDesc('id'),
            ProductSort::STYLE_DESC => $this->orderByDesc($this->beerStyleNameSubquery())->orderByDesc('id'),
            ProductSort::RATING_ASC => $this->orderBy($this->untappdRatingSubquery())->orderByDesc('id'),
            ProductSort::RATING_DESC => $this->orderByDesc($this->untappdRatingSubquery())->orderByDesc('id'),
        };
    }

    private function manufacturerNameSubquery(): Builder
    {
        return Manufacturer::query()->select('name')->whereColumn('id', 'products.manufacturer_id');
    }

    private function beerStyleNameSubquery(): Builder
    {
        return BeerProductDetail::query()
            ->join('beer_styles', 'beer_styles.id', '=', 'beer_product_details.beer_style_id')
            ->whereColumn('beer_product_details.product_id', 'products.id')
            ->select('beer_styles.name');
    }

    private function untappdRatingSubquery(): Builder
    {
        return BeerProductDetail::query()
            ->join('untappd_beers', 'untappd_beers.id', '=', 'beer_product_details.untappd_beer_id')
            ->whereColumn('beer_product_details.product_id', 'products.id')
            ->select('untappd_beers.rating_score');
    }
}
