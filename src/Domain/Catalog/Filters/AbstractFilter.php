<?php

declare(strict_types=1);

namespace Domain\Catalog\Filters;

use Closure;
use Domain\Catalog\Builders\ProductBuilder;
use Stringable;

/**
 * Самоописывающийся фильтр каталога: знает своё имя поля запроса, умеет
 * применить себя к Eloquent-запросу и умеет отрендерить свой собственный
 * Blade-виджет (через __toString()/view()). Регистрируется в FilterManager
 * и применяется через Illuminate\Pipeline\Pipeline — __invoke() как раз под
 * это заточен.
 *
 * Типизирован конкретно под ProductBuilder, не общий Eloquent Builder — эти
 * фильтры применяются только к Product, а ProductBuilder несёт нужные им
 * методы (published()/inCategories() и т.д.).
 *
 * Схема GET-параметров плоская (?categories[]=1&price_min=100&q=...), как
 * уже сложилось на сайте — НЕ namespaced filters[key] с другого проекта,
 * откуда принесён этот каркас. requestValue()/name()/id() рассчитаны именно
 * на плоскую схему.
 */
abstract class AbstractFilter implements Stringable
{
    public function __invoke(ProductBuilder $query, Closure $next): mixed
    {
        // Скоупы Product мутируют и возвращают $this (fluent), но контракт
        // пайплайна не должен на этом молчаливо держаться — прокидываем
        // результат apply() дальше явно.
        return $next($this->apply($query));
    }

    abstract public function title(): string;

    abstract public function key(): string;

    abstract public function apply(ProductBuilder $query): ProductBuilder;

    abstract public function values(): array;

    abstract public function view(): string;

    /**
     * Правила валидации Laravel для полей, которыми владеет этот фильтр —
     * слитый результат уходит в CatalogFilterRequest через FilterManager::rules().
     */
    abstract public function rules(): array;

    /**
     * Показывать ли фильтр и применять ли его вообще. false — виджет не
     * рендерится (FilterManager::items()), значение из request() не
     * применяется к запросу (apply() пропускается в пайплайне) и правило
     * валидации не подмешивается — см. FilterManager.
     */
    public function visible(): bool
    {
        return true;
    }

    public function requestValue(?string $index = null, mixed $default = null): mixed
    {
        return request($this->key().($index ? '.'.$index : ''), $default);
    }

    public function name(?string $index = null): string
    {
        return $this->key().'['.($index ?? '').']';
    }

    public function id(?string $index = null): string
    {
        return str($this->name($index))
            ->slug('_')
            ->value();
    }

    public function __toString(): string
    {
        return view($this->view(), [
            'filter' => $this,
        ])->render();
    }
}
