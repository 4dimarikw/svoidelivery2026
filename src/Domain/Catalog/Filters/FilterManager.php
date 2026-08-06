<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;
use Illuminate\Pipeline\Pipeline;

final class FilterManager
{
    public function __construct(
        protected array $items = []
    ) {}

    public function registerFilters(array $items): void
    {
        $this->items = $items;
    }

    public function items(): array
    {
        return $this->items;
    }

    /**
     * Прогоняет запрос через все зарегистрированные фильтры по очереди —
     * каждый читает своё значение из request() и накладывает условие
     * (см. AbstractFilter::__invoke()).
     */
    public function apply(ProductBuilder $query): ProductBuilder
    {
        return app(Pipeline::class)
            ->send($query)
            ->through($this->items)
            ->then(fn (ProductBuilder $q): ProductBuilder => $q);
    }

    /**
     * Слитые правила валидации всех зарегистрированных фильтров — источник
     * правды для CatalogFilterRequest::rules(), чтобы правило жило рядом с
     * полем, которым фильтр владеет, а не дублировалось отдельно.
     */
    public function rules(): array
    {
        return collect($this->items)
            ->flatMap(fn (AbstractFilter $filter) => $filter->rules())
            ->all();
    }
}
