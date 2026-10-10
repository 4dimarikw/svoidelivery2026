<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Seo\Pages;

use App\MoonShine\Resources\Seo\SeoFields;
use App\MoonShine\Resources\Seo\SeoResource;
use Dimarik\LaravelSeo\Models\Seo;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Table\TableBuilder;

/**
 * @extends IndexPage<SeoResource>
 */
class SeoIndexPage extends IndexPage
{
    protected bool $isLazy = true;

    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return SeoFields::index();
    }

    /**
     * @return list<FieldContract>
     */
    public function filters(): array
    {
        return SeoFields::filters();
    }

    public function buttons(): ListOf
    {
        return parent::buttons()
            ->add(
                ActionButton::make(__('laravel-seo::moonshine.open_page'), static fn (Seo $item) => $item->url)
                    ->icon('arrow-top-right-on-square')
                    ->blank()
            );
    }

    /**
     * @param  TableBuilder  $component
     * @return TableBuilder
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component->columnSelection();
    }
}
