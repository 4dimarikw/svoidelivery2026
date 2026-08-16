<?php

namespace Domain\Content\Types\examples;

use Domain\Content\Data\ContentItemGroup;
use Domain\Content\Rules\SafeContentUrl;
use Domain\Content\Types\AbstractContentBlockType;

final class LocationBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'location';
    }

    public function label(): string
    {
        return 'Место';
    }

    public function blockFields(): array
    {
        return [
            $this->text('Надзаголовок', 'eyebrow'),
            $this->text('Заголовок', 'heading'),
            $this->textarea('Описание', 'description'),
            $this->text('Адрес', 'address'),
            $this->url('Ссылка на карту', 'map_url'),
            $this->text('Прогноз погоды', 'weather'),
        ];
    }

    public function blockRules(): array
    {
        return ['map_url' => ['nullable', new SafeContentUrl]];
    }

    public function itemGroups(): array
    {
        return [
            'routes' => new ContentItemGroup('routes', 'Как добраться', [
                $this->text('Название', 'heading'),
                $this->text('Время в пути', 'time'),
                $this->textarea('Описание', 'description'),
                $this->icon(),
            ], ['heading' => ['required', 'string', 'max:255']]),
            'amenities' => new ContentItemGroup('amenities', 'Удобства', [
                $this->text('Название', 'heading'),
                $this->textarea('Описание', 'description'),
                $this->icon(),
            ], ['heading' => ['required', 'string', 'max:255']]),
        ];
    }

    public function mediaCollections(): array
    {
        return ['map', 'image'];
    }
}
