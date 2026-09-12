<?php

namespace Tests\Fixtures\Content;

use ContentCms\Content\Data\ContentItemGroup;
use Domain\Content\Types\AbstractContentBlockType;

final class IntegrationBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'integration';
    }

    public function label(): string
    {
        return 'Integration';
    }

    public function blockFields(): array
    {
        return [$this->text('Heading', 'heading')];
    }

    public function blockRules(): array
    {
        return ['heading' => ['required', 'string']];
    }

    public function mediaCollections(): array
    {
        return ['cover'];
    }

    public function fileCollections(): array
    {
        return ['document'];
    }

    public function itemGroups(): array
    {
        return ['cards' => new ContentItemGroup(
            'cards', 'Cards', [$this->text('Text', 'text')],
            ['text' => ['required', 'string']], ['image'],
        )];
    }
}
