<?php

namespace Domain\Content\Types;

use Domain\Content\Data\ContentItemGroup;
use Domain\Content\Rules\SafeContentUrl;
use MoonShine\UI\Fields\Date;

final class MarketBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'market';
    }

    public function label(): string
    {
        return 'Маркет';
    }

    public function blockFields(): array
    {
        return [
            $this->text('Надзаголовок', 'eyebrow'),
            $this->text('Заголовок', 'heading'),
            $this->textarea('Описание', 'description'),
            Date::make('Дедлайн заявок', 'deadline')->withTime(),
            $this->text('Заголовок заявки', 'application_heading'),
            $this->textarea('Текст заявки', 'application_text'),
            $this->textarea('Шаблон Telegram-сообщения', 'message_template'),
            $this->url('Ссылка для заявки', 'application_url'),
        ];
    }

    public function blockRules(): array
    {
        return ['application_url' => ['nullable', new SafeContentUrl]];
    }

    public function itemGroups(): array
    {
        return [
            'categories' => new ContentItemGroup('categories', 'Категории', [
                $this->text('Название', 'heading'),
                $this->textarea('Примеры', 'examples'),
                $this->icon(),
            ], ['heading' => ['required', 'string', 'max:255']]),
            'benefits' => new ContentItemGroup('benefits', 'Преимущества', [
                $this->text('Заголовок', 'heading'),
                $this->textarea('Описание', 'description'),
                $this->icon(),
            ], ['heading' => ['required', 'string', 'max:255']]),
        ];
    }

    public function mediaCollections(): array
    {
        return ['background'];
    }
}
