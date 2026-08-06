<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;
use Domain\Catalog\Models\BeerStyle;

final class BeerStyleFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Стиль';
    }

    public function key(): string
    {
        return 'beer_styles';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->ofBeerStyles((array) $this->requestValue(default: []));
    }

    public function values(): array
    {
        return BeerStyle::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function view(): string
    {
        return 'pages.catalog.filters.select';
    }

    public function rules(): array
    {
        return [
            'beer_styles' => ['nullable', 'array'],
            'beer_styles.*' => ['integer', 'exists:beer_styles,id'],
        ];
    }
}
