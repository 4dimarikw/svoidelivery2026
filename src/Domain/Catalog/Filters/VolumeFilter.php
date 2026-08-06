<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;
use Domain\Catalog\Models\Volume;

final class VolumeFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Объём';
    }

    public function key(): string
    {
        return 'volumes';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->ofVolumes((array) $this->requestValue(default: []));
    }

    public function values(): array
    {
        // У Volume нет is_active, в отличие от остальных справочников.
        return Volume::query()->orderBy('milliliters')->pluck('label', 'id')->all();
    }

    public function view(): string
    {
        return 'pages.catalog.filters.select';
    }

    public function rules(): array
    {
        return [
            'volumes' => ['nullable', 'array'],
            'volumes.*' => ['integer', 'exists:volumes,id'],
        ];
    }
}
