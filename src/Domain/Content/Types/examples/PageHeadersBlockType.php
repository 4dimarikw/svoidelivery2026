<?php

namespace Domain\Content\Types\examples;

use Domain\Content\Types\AbstractContentBlockType;

final class PageHeadersBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'page_headers';
    }

    public function label(): string
    {
        return 'Заголовок раздела сайта';
    }

    public function blockFields(): array
    {
        return [
            $this->text('Заголовок', 'heading'),
            $this->text('Подзаголовок', 'subheading'),
        ];
    }

    public function blockRules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:255'],
            'subheading' => ['nullable', 'string', 'max:255'],
        ];
    }

}
