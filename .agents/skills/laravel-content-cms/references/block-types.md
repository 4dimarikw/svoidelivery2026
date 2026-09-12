# Примеры прикладных типов

Каждый пример PHP-класса — отдельный файл приложения в `app/Content/Types`. Классы используют реальные контракты пакета. Не копируйте примеры как встроенные типы в ядро CMS.

## Простой текстовый блок

```php
<?php

namespace App\Content\Types;

use ContentCms\Content\Types\AbstractContentBlockType;

final class TextBlockType extends AbstractContentBlockType
{
    public function key(): string { return 'text'; }
    public function label(): string { return 'Текст'; }

    public function blockFields(): array
    {
        return [$this->text('Заголовок', 'heading'), $this->textarea('Текст', 'body')];
    }

    public function blockRules(): array
    {
        return [
            'heading' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
```

## Блок с карточками, изображением, файлом и ссылкой

```php
<?php

namespace App\Content\Types;

use ContentCms\Content\Data\ContentItemGroup;
use ContentCms\Content\Types\AbstractContentBlockType;
use ContentCms\Rules\SafeContentUrl;

final class CardsBlockType extends AbstractContentBlockType
{
    public function key(): string { return 'cards'; }
    public function label(): string { return 'Карточки'; }

    public function blockFields(): array
    {
        return [$this->text('Заголовок', 'heading')];
    }

    public function blockRules(): array
    {
        return ['heading' => ['required', 'string', 'max:255']];
    }

    public function itemGroups(): array
    {
        return ['cards' => new ContentItemGroup(
            key: 'cards',
            label: 'Карточки',
            fields: [
                $this->text('Название', 'label'),
                $this->textarea('Описание', 'description'),
                $this->text('Ссылка', 'link'),
            ],
            rules: [
                'label' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'link' => ['nullable', 'string', 'max:2048', new SafeContentUrl],
            ],
            mediaCollections: ['image'],
        )];
    }

    public function mediaCollections(): array { return ['cover']; }
    public function fileCollections(): array { return ['document']; }
}
```

Для локального пути/якоря используется текстовое поле и SafeContentUrl, чтобы браузерное поле URL не требовало абсолютный адрес. Правила задаются без `content.`: форма сама добавляет этот префикс.

Объявление коллекций создаёт поля MediaLibrary в форме: `cover` — изображение блока, `document` — файл, `image` — изображение карточки. Коллекции одиночные. Формы изображений допускают JPEG/PNG/WebP, файлы блока — также PDF. У ContentItemGroup нет параметра fileCollections.

## Регистрация и данные

В `config/content-cms.php` добавьте оба класса в существующий список `types`, сохранив остальные настройки:

```php
'types' => [
    App\Content\Types\TextBlockType::class,
    App\Content\Types\CardsBlockType::class,
],
```

При использовании кешированной конфигурации обновите кеш штатным процессом приложения. Реестр singleton; изменять конфигурацию после его разрешения в середине запроса не следует.

В MoonShine создайте раздел с существующим именованным GET-маршрутом, затем блок нужного типа. Сохраните основные поля, откройте редактирование и заполните содержимое. Элемент группы создаётся аналогично в два этапа. Отдельный ресурс для каждого типа не нужен.

В тестах приложения после регистрации типов можно использовать:

```php
$block = \ContentCms\Models\ContentBlock::factory()->ofType('cards')->create(['is_active' => false]);
$item = \ContentCms\Models\ContentBlockItem::factory()->inGroup($block, 'cards')->create();
```

## Публичный вывод

В контроллере передайте данные в собственный view приложения:

```php
return view('content-page', [
    'sections' => app(\ContentCms\Actions\Content\LoadPublishedSections::class)->handle('home'),
    'menu' => app(\ContentCms\Actions\Menu\BuildMenu::class)->handle('main'),
]);
```

В `content-page.blade.php` используйте явную карту, а не имя view из JSON:

```blade
@php($views = ['text' => 'blocks.text', 'cards' => 'blocks.cards'])
@foreach ($sections as $section)
    @foreach ($section->publishedBlocks as $block)
        @if (isset($views[$block->type]) && view()->exists($views[$block->type]))
            @include($views[$block->type], ['block' => $block])
        @endif
    @endforeach
@endforeach
```

`blocks/text.blade.php`:

```blade
<section>
    <h2>{{ $block->content['heading'] ?? '' }}</h2>
    <p>{{ $block->content['body'] ?? '' }}</p>
</section>
```

`blocks/cards.blade.php`:

```blade
<section>
    <h2>{{ $block->content['heading'] ?? '' }}</h2>
    @if ($cover = $block->getFirstMediaUrl('cover'))
        <img src="{{ $cover }}" alt="">
    @endif
    @if ($document = $block->getFirstMediaUrl('document'))
        <a href="{{ $document }}">Скачать документ</a>
    @endif
    @foreach ($block->publishedItems->where('group_key', 'cards') as $item)
        <article>
            @if ($image = $item->getFirstMediaUrl('image'))
                <img src="{{ $image }}" alt="{{ $item->content['label'] ?? '' }}">
            @endif
            <h3>{{ $item->content['label'] ?? '' }}</h3>
            <p>{{ $item->content['description'] ?? '' }}</p>
            @if ($url = \ContentCms\Support\SafeContentUrl::resolve($item->content['link'] ?? null))
                <a href="{{ $url }}">Подробнее</a>
            @endif
        </article>
    @endforeach
</section>
```

`publishedItems` и media уже загружены; фильтрация группы здесь выполняется в памяти. Для новых полей сохраняйте безопасные значения по умолчанию, не выводите CMS-текст через `{!! !!}`.
