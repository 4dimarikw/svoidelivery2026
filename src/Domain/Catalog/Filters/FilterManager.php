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
        return $this->visibleItems();
    }

    /**
     * Прогоняет запрос через все зарегистрированные фильтры по очереди —
     * каждый читает своё значение из request() и накладывает условие
     * (см. AbstractFilter::__invoke()). Невидимые (AbstractFilter::visible()
     * === false, например PriceRangeFilter для гостя) пропускаются —
     * значение из request() для них игнорируется, даже если проставлено
     * руками в URL.
     */
    public function apply(ProductBuilder $query): ProductBuilder
    {
        return app(Pipeline::class)
            ->send($query)
            ->through($this->visibleItems())
            ->then(fn (ProductBuilder $q): ProductBuilder => $q);
    }

    /**
     * Слитые правила валидации всех зарегистрированных фильтров — источник
     * правды для CatalogFilterRequest::rules(), чтобы правило жило рядом с
     * полем, которым фильтр владеет, а не дублировалось отдельно. Невидимый
     * фильтр не подмешивает правило — его поле просто игнорируется
     * Laravel'ом, а не 422-ится.
     */
    public function rules(): array
    {
        return collect($this->visibleItems())
            ->flatMap(fn (AbstractFilter $filter) => $filter->rules())
            ->all();
    }

    private function visibleItems(): array
    {
        return array_values(array_filter($this->items, fn (AbstractFilter $filter) => $filter->visible()));
    }
}
