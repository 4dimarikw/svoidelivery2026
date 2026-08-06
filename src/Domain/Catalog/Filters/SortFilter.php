<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;
use Domain\Catalog\Enums\ProductSort;
use Illuminate\Validation\Rule;

final class SortFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Сортировка';
    }

    public function key(): string
    {
        return 'sort';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        $sort = ProductSort::tryFrom((string) $this->requestValue());

        if ($sort === null || ! in_array($sort, $this->availableCases(), true)) {
            $sort = ProductSort::default();
        }

        return $query->sorted($sort);
    }

    public function values(): array
    {
        return collect($this->availableCases())->mapWithKeys(fn (ProductSort $sort) => [$sort->value => $sort->label()])->all();
    }

    /**
     * Цена скрыта от гостя (см. product-card.blade.php), значит и
     * сортировка по ней недоступна — иначе гость восстанавливает порядок
     * цен через `?sort=price_asc` даже без доступа к PriceRangeFilter.
     * Сам SortFilter остаётся видимым всегда (он задаёт порядок выдачи),
     * гейтятся только эти два кейса. rules() не трогаем — ?sort=price_asc
     * от гостя должен тихо деградировать к дефолту, а не 422-иться на
     * старой закладке/шаренной ссылке.
     */
    private function availableCases(): array
    {
        if (auth()->check()) {
            return ProductSort::cases();
        }

        return array_values(array_filter(
            ProductSort::cases(),
            fn (ProductSort $sort) => ! in_array($sort, [ProductSort::PRICE_ASC, ProductSort::PRICE_DESC], true)
        ));
    }

    public function view(): string
    {
        return 'pages.catalog.filters.sort';
    }

    public function rules(): array
    {
        return [
            'sort' => ['nullable', Rule::enum(ProductSort::class)],
        ];
    }
}
