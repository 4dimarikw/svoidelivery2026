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
        return $query->sorted(ProductSort::tryFrom((string) $this->requestValue()) ?? ProductSort::default());
    }

    public function values(): array
    {
        return collect(ProductSort::cases())->mapWithKeys(fn (ProductSort $sort) => [$sort->value => $sort->label()])->all();
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
