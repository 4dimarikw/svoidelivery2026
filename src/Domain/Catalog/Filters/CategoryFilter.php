<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;
use Domain\Catalog\Models\Category;

final class CategoryFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Категория';
    }

    public function key(): string
    {
        return 'categories';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->inCategories((array) $this->requestValue(default: []));
    }

    public function values(): array
    {
        return Category::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function view(): string
    {
        return 'pages.catalog.filters.select';
    }

    public function rules(): array
    {
        return [
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
