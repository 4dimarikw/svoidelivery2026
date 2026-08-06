<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;

final class InStockFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Только в наличии';
    }

    public function key(): string
    {
        return 'in_stock';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->onlyInStock(request()->boolean('in_stock'));
    }

    public function values(): array
    {
        return [];
    }

    public function view(): string
    {
        return 'pages.catalog.filters.in-stock';
    }

    public function rules(): array
    {
        return ['in_stock' => ['nullable', 'boolean']];
    }
}
