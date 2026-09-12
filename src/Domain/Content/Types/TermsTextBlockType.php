<?php

namespace Domain\Content\Types;

use MoonShine\TinyMce\Fields\TinyMce;

final class TermsTextBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'terms_text';
    }

    public function label(): string
    {
        return 'Текст условий сервиса';
    }

    public function blockFields(): array
    {
        return [TinyMce::make('Текст условий', 'body')
            ->plugins(['lists', 'link'])
            ->menubar(false)
            ->toolbar('undo redo | blocks | bold italic underline | bullist numlist | link removeformat')
            ->addOption('block_formats', 'Абзац=p; Подзаголовок=h2; Подзаголовок второго уровня=h3')];
    }

    public function blockRules(): array
    {
        return ['body' => ['nullable', 'string', 'max:100000']];
    }
}
