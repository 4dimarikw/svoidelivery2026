<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;
use Illuminate\Validation\Rule;

/**
 * Не ложится под requestValue()/name() из AbstractFilter — это два отдельных
 * скалярных поля (abv_min/abv_max), а не "один ключ → массив id", поэтому
 * читает request() напрямую по своим именам полей. Тот же приём, что
 * PriceRangeFilter.
 */
final class AbvRangeFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Крепость, % ABV';
    }

    public function key(): string
    {
        return 'abv';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->withAbvBetween(request('abv_min'), request('abv_max'));
    }

    public function values(): array
    {
        return [];
    }

    public function view(): string
    {
        return 'pages.catalog.filters.abv-range';
    }

    public function rules(): array
    {
        return [
            'abv_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // gte:abv_min — только когда abv_min реально заполнен, см.
            // комментарий в PriceRangeFilter::rules() (тот же баг/фикс).
            'abv_max' => [
                'nullable', 'numeric', 'min:0', 'max:100',
                Rule::when(request()->filled('abv_min'), ['gte:abv_min']),
            ],
        ];
    }
}
