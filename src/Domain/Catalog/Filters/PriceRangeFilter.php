<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;

/**
 * Не ложится под requestValue()/name() из AbstractFilter — это два отдельных
 * скалярных поля (price_min/price_max), а не "один ключ → массив id",
 * поэтому читает request() напрямую по своим именам полей.
 */
final class PriceRangeFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Цена, ₽';
    }

    public function key(): string
    {
        return 'price';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->inPriceRange(request('price_min'), request('price_max'));
    }

    public function values(): array
    {
        return [];
    }

    public function view(): string
    {
        return 'pages.catalog.filters.price-range';
    }

    public function rules(): array
    {
        return [
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'gte:price_min'],
        ];
    }
}
