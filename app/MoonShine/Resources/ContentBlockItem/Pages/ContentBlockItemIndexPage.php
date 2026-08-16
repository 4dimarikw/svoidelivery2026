<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ContentBlockItem\Pages;

use App\MoonShine\Resources\ContentBlock\ContentBlockResource;
use App\MoonShine\Resources\ContentBlockItem\ContentBlockItemResource;
use Domain\Content\ContentBlockTypeRegistry;
use Domain\Content\Models\ContentBlock;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/** @extends IndexPage<ContentBlockItemResource> */
final class ContentBlockItemIndexPage extends IndexPage
{
    protected function fields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Блок', 'block', formatted: static fn(ContentBlock $block) => $block->title, resource: ContentBlockResource::class),
            Text::make('Название', 'title'),
            Text::make('Группа', 'group_key'),
            Text::make('Ключ', 'key'),
            Number::make('Порядок', 'sort_order')->sortable(),
            Switcher::make('Активен', 'is_active'),
        ];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Блок', 'block', formatted: static fn(ContentBlock $block) => $block->title, resource: ContentBlockResource::class)->nullable(),
            Select::make('Группа', 'group_key')->options($this->groupOptions())->nullable(),
            Switcher::make('Активен', 'is_active'),
        ];
    }

    /** @return array<string, string> */
    private function groupOptions(): array
    {
        $options = [];

        foreach (app(ContentBlockTypeRegistry::class)->all() as $type) {
            foreach ($type->itemGroups() as $group) {
                $options[$group->key] = $group->label;
            }
        }

        return $options;
    }
}
