<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;
use Domain\Catalog\Models\Manufacturer;

final class ManufacturerFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Производитель';
    }

    public function key(): string
    {
        return 'manufacturers';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->ofManufacturers((array) $this->requestValue(default: []));
    }

    public function values(): array
    {
        return Manufacturer::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function view(): string
    {
        return 'pages.catalog.filters.select';
    }

    public function rules(): array
    {
        return [
            'manufacturers' => ['nullable', 'array'],
            'manufacturers.*' => ['integer', 'exists:manufacturers,id'],
        ];
    }
}
