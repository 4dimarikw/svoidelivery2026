<?php

namespace Domain\Content\Types;

use Domain\Content\Data\ContentItemGroup;
use Domain\Content\Rules\SafeContentUrl;
use MoonShine\UI\Fields\Number;

final class CharityBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'charity';
    }

    public function label(): string
    {
        return 'Благотворительность';
    }

    public function blockFields(): array
    {
        return [
            $this->text('Надзаголовок', 'eyebrow'),
            $this->text('Заголовок', 'heading'),
            $this->textarea('Описание', 'description'),
            $this->text('Подпись счётчика', 'counter_label'),
            Number::make('Целевое значение', 'counter_target')->min(0),
            $this->text('Текст кнопки', 'cta_label'),
            $this->url('Ссылка кнопки', 'cta_url'),
        ];
    }

    public function itemGroups(): array
    {
        return [
            'wishlist' => new ContentItemGroup('wishlist', 'Список необходимого', [
                $this->text('Название', 'name'),
                $this->textarea('Описание', 'description'),
                $this->icon(),
            ], ['name' => ['required', 'string', 'max:255']]),
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
