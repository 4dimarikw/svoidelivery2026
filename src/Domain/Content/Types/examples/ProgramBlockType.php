<?php

namespace Domain\Content\Types\examples;

use Domain\Content\Data\ContentItemGroup;
use Domain\Content\Types\AbstractContentBlockType;
use MoonShine\UI\Fields\Json;

final class ProgramBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'program';
    }

    public function label(): string
    {
        return 'Программа';
    }

    public function blockFields(): array
    {
        return [
            $this->text('Надзаголовок', 'eyebrow'),
            $this->text('Заголовок', 'heading'),
            $this->textarea('Описание', 'description'),
        ];
    }

    public function itemGroups(): array
    {
        return [
            'categories' => new ContentItemGroup('categories', 'Категории', [
                $this->text('Название', 'name'),
                $this->icon(),
            ], ['name' => ['required', 'string', 'max:100']]),
            'activities' => new ContentItemGroup('activities', 'Активности', [
                $this->text('Название', 'heading'),
                $this->text('Категория', 'category'),
                $this->text('Время', 'time'),
                $this->textarea('Описание', 'description'),
                $this->text('Метка', 'badge'),
                Json::make('Особенности', 'highlights')->onlyValue('Текст')->creatable()->reorderable(),
                $this->icon(),
            ], ['heading' => ['required', 'string', 'max:255']], ['image']),
        ];
    }
}
