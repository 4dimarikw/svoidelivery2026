<?php

namespace Tests\Fixtures\Content;

use ContentCms\Content\Data\ContentItemGroup;
use Domain\Content\Types\AbstractContentBlockType;

final class ItemsBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'test_items';
    }

    public function label(): string
    {
        return 'Тестовый блок с элементами';
    }

    public function blockFields(): array
    {
        return [$this->text('Текст', 'text')];
    }

    public function itemGroups(): array
    {
        return [
            'ordering_rules' => new ContentItemGroup('ordering_rules', 'Правила', [
                $this->text('Текст пункта', 'text'),
            ], ['text' => ['required', 'string', 'max:500']]),
        ];
    }
}
