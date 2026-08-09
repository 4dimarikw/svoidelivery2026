<?php

namespace Domain\Content\Types;

use Domain\Content\Data\ContentItemGroup;
use Domain\Content\Rules\SafeContentUrl;

final class AboutBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'about';
    }

    public function label(): string
    {
        return 'О событии';
    }

    public function blockFields(): array
    {
        return [
            $this->text('Надзаголовок', 'eyebrow'),
            $this->text('Заголовок', 'heading'),
            $this->textarea('Описание', 'description'),
            $this->text('Заголовок истории', 'story_heading'),
            $this->textarea('История', 'story'),
            $this->text('Текст кнопки', 'cta_label'),
            $this->url('Ссылка кнопки', 'cta_url'),
        ];
    }

    public function itemGroups(): array
    {
        return [
            'benefits' => new ContentItemGroup('benefits', 'Преимущества', [
                $this->text('Заголовок', 'heading'),
                $this->textarea('Описание', 'description'),
                $this->icon(),
            ], ['heading' => ['required', 'string', 'max:255']]),
        ];
    }

    public function blockRules(): array
    {
        return ['cta_url' => ['nullable', new SafeContentUrl]];
    }

    public function mediaCollections(): array
    {
        return ['image'];
    }
}
