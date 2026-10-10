<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Seo\Pages;

use App\MoonShine\Resources\Seo\SeoFields;
use App\MoonShine\Resources\Seo\SeoResource;
use Dimarik\LaravelSeo\Models\Seo;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;

/**
 * @extends FormPage<SeoResource>
 */
class SeoFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return SeoFields::form();
    }

    public function buttons(): ListOf
    {
        return parent::buttons()
            ->prepend(
                ActionButton::make(__('laravel-seo::moonshine.open_page'), static fn (Seo $item) => $item->url)
                    ->icon('arrow-top-right-on-square')
                    ->blank()
            );
    }

    /** @param DataWrapperContract<Seo> $item */
    protected function rules(DataWrapperContract $item): array
    {
        return SeoFields::rules($item);
    }
}
