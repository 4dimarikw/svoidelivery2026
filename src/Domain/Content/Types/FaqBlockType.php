<?php

namespace Domain\Content\Types;

use Domain\Content\Data\ContentItemGroup;
use Domain\Content\Rules\SafeContentUrl;

final class FaqBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'faq';
    }

    public function label(): string
    {
        return 'Вопросы и ответы';
    }

    public function blockFields(): array
    {
        return [
            $this->text('Надзаголовок', 'eyebrow'),
            $this->text('Заголовок', 'heading'),
            $this->textarea('Описание', 'description'),
            $this->text('Подсказка поиска', 'search_placeholder'),
            $this->text('Текст связи', 'contact_label'),
            $this->url('Ссылка для связи', 'contact_url'),
        ];
    }

    public function blockRules(): array
    {
        return ['contact_url' => ['nullable', new SafeContentUrl]];
    }

    public function itemGroups(): array
    {
        return [
            'questions' => new ContentItemGroup('questions', 'Вопросы', [
                $this->text('Вопрос', 'question'),
                $this->textarea('Ответ', 'answer'),
                $this->text('Категория', 'category'),
            ], [
                'question' => ['required', 'string', 'max:255'],
                'answer' => ['required', 'string'],
            ]),
        ];
    }
}
