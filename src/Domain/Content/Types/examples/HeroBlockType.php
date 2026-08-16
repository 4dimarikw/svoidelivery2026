<?php

namespace Domain\Content\Types\examples;

use Domain\Content\Data\ContentItemGroup;
use Domain\Content\Rules\SafeContentUrl;
use Domain\Content\Types\AbstractContentBlockType;
use MoonShine\UI\Fields\Date;

final class HeroBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return 'Первый экран';
    }

    public function blockFields(): array
    {
        return [
            $this->text('Надзаголовок', 'eyebrow'),
            $this->text('Заголовок', 'heading'),
            $this->text('Слоган', 'tagline'),
            $this->textarea('Описание', 'description'),
            Date::make('Начало', 'starts_at')->withTime(),
            Date::make('Окончание', 'ends_at')->withTime(),
            $this->text('Место', 'location'),
            $this->text('Основная кнопка', 'primary_cta_label'),
            $this->url('Ссылка основной кнопки', 'primary_cta_url'),
            $this->text('Дополнительная кнопка', 'secondary_cta_label'),
            $this->url('Ссылка дополнительной кнопки', 'secondary_cta_url'),
        ];
    }

    public function blockRules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:255'],
            'primary_cta_url' => ['nullable', new SafeContentUrl],
            'secondary_cta_url' => ['nullable', new SafeContentUrl],
        ];
    }

    public function itemGroups(): array
    {
        return [
            'tags' => new ContentItemGroup('tags', 'Теги', [
                $this->text('Текст', 'text'),
                $this->icon(),
            ], ['text' => ['required', 'string', 'max:100']]),
        ];
    }

    public function mediaCollections(): array
    {
        return ['background'];
    }
}
