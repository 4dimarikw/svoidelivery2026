<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;

final class ContainerFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Тара';
    }

    public function key(): string
    {
        return 'containers';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->ofContainers((array) $this->requestValue(default: []));
    }

    public function values(): array
    {
        return FilterOptionsRegistry::for('containers');
    }

    public function view(): string
    {
        return 'pages.catalog.filters.select';
    }

    public function rules(): array
    {
        return [
            'containers' => ['nullable', 'array'],
            'containers.*' => ['integer', 'exists:containers,id'],
        ];
    }
}
