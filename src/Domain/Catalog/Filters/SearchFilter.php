<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;

final class SearchFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Название товара';
    }

    public function key(): string
    {
        return 'q';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->search(request('q'));
    }

    public function values(): array
    {
        return [];
    }

    public function view(): string
    {
        return 'pages.catalog.filters.search';
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:255']];
    }
}
