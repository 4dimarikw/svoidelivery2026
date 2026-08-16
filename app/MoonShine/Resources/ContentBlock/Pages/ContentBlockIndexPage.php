<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ContentBlock\Pages;

use App\MoonShine\Resources\ContentBlock\ContentBlockResource;
use App\MoonShine\Resources\SiteSection\SiteSectionResource;
use Domain\Content\ContentBlockTypeRegistry;
use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\SiteSection;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/** @extends IndexPage<ContentBlockResource> */
final class ContentBlockIndexPage extends IndexPage
{
    protected function fields(): iterable
    {
        $types = app(ContentBlockTypeRegistry::class);

        return [
            ID::make(),
            BelongsTo::make('Раздел', 'section', formatted: static fn(SiteSection $section) => $section->title, resource: SiteSectionResource::class),
            Text::make('Название', 'title'),
            Text::make('Ключ', 'key'),
            Text::make('Тип', 'type', formatted: static fn(ContentBlock $block) => $types->get($block->type)?->label() ?? '⚠ Неизвестный: ' . $block->type),
            Number::make('Порядок', 'sort_order')->sortable(),
            Switcher::make('Активен', 'is_active'),
        ];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Раздел', 'section', formatted: static fn(SiteSection $section) => $section->title, resource: SiteSectionResource::class),
            Select::make('Тип', 'type')->options(app(ContentBlockTypeRegistry::class)->options()),
            Switcher::make('Активен', 'is_active'),
        ];
    }
}
