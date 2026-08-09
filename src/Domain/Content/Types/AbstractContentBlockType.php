<?php

namespace Domain\Content\Types;

use Domain\Content\Contracts\ContentBlockType;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Url;

abstract class AbstractContentBlockType implements ContentBlockType
{
    protected function text(string $label, string $key): Text
    {
        return Text::make($label, $key);
    }

    protected function textarea(string $label, string $key): Textarea
    {
        return Textarea::make($label, $key);
    }

    protected function url(string $label, string $key): Url
    {
        return Url::make($label, $key);
    }

    protected function icon(string $label = 'Иконка', string $key = 'icon'): Select
    {
        $icons = config('content.icons', []);

        return Select::make($label, $key)->options(array_combine($icons, $icons) ?: []);
    }

    public function blockRules(): array
    {
        return [];
    }

    public function itemGroups(): array
    {
        return [];
    }

    public function mediaCollections(): array
    {
        return [];
    }
}
