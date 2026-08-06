<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;

/**
 * Не ложится под requestValue()/name() из AbstractFilter — это два отдельных
 * скалярных поля (ibu_min/ibu_max), а не "один ключ → массив id", поэтому
 * читает request() напрямую по своим именам полей. Тот же приём, что
 * PriceRangeFilter.
 */
final class IbuRangeFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Горечь, IBU';
    }

    public function key(): string
    {
        return 'ibu';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->withIbuBetween(request('ibu_min'), request('ibu_max'));
    }

    public function values(): array
    {
        return [];
    }

    public function view(): string
    {
        return 'pages.catalog.filters.ibu-range';
    }

    public function rules(): array
    {
        // Верхней границы намеренно нет — шкала IBU не нормирована в проекте,
        // угадывать разумный максимум не буду (в отличие от ABV — это проценты).
        return [
            'ibu_min' => ['nullable', 'numeric', 'min:0'],
            'ibu_max' => ['nullable', 'numeric', 'min:0', 'gte:ibu_min'],
        ];
    }
}
