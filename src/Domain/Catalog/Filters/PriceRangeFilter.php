<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Domain\Catalog\Builders\ProductBuilder;
use Illuminate\Validation\Rule;

/**
 * Не ложится под requestValue()/name() из AbstractFilter — это два отдельных
 * скалярных поля (price_min/price_max), а не "один ключ → массив id",
 * поэтому читает request() напрямую по своим именам полей.
 */
final class PriceRangeFilter extends AbstractFilter
{
    public function title(): string
    {
        return 'Цена, ₽';
    }

    public function key(): string
    {
        return 'price';
    }

    public function apply(ProductBuilder $query): ProductBuilder
    {
        return $query->inPriceRange(request('price_min'), request('price_max'));
    }

    public function values(): array
    {
        return [];
    }

    public function view(): string
    {
        return 'pages.catalog.filters.price-range';
    }

    public function rules(): array
    {
        return [
            'price_min' => ['nullable', 'numeric', 'min:0'],
            // gte:price_min — только когда price_min реально заполнен.
            // ConvertEmptyStringsToNull превращает пустое поле формы в null,
            // а validateGte() сравнивает типы (string !== NULL) раньше, чем
            // значения — без этой обёртки заполнение одного «до» без «от»
            // 422-ит весь запрос, и фильтр молча не применяется вовсе.
            'price_max' => [
                'nullable', 'numeric', 'min:0',
                Rule::when(request()->filled('price_min'), ['gte:price_min']),
            ],
        ];
    }

    /**
     * Цена скрыта от гостя на карточке товара (product-card.blade.php) —
     * значит и фильтр по ней должен быть недоступен, иначе подбор диапазона
     * работает как оракул цены и скрытие становится декоративным.
     */
    public function visible(): bool
    {
        return auth()->check();
    }
}
