<?php

namespace Domain\Content\Types;

use ContentCms\Content\Types\AbstractContentBlockType as PackageBlockType;
use MoonShine\UI\Fields\Select;

abstract class AbstractContentBlockType extends PackageBlockType
{
    protected function icon(string $label = 'Иконка', string $key = 'icon'): Select
    {
        $icons = config('content.icons', []);

        return Select::make($label, $key)->options(array_combine($icons, $icons) ?: []);
    }
}
