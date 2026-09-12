# Вывод Laravel Content CMS на страницах сайта

Руководство для приложения с `dimarik/laravel-content-cms`: Laravel 12, PHP 8.4, MoonShine 4 и Spatie Media Library. Пакет хранит контент и даёт административные формы; маршруты, контроллеры, Blade и дизайн создаются в приложении. Команда публикации копирует только этот документ, не устанавливает примеры и не меняет БД.

## 1. Подготовьте типы контента

Ниже полный минимальный набор типов для примеров. Создайте два файла приложения. Если такие типы уже существуют, адаптируйте их имена и поля вместо повторной регистрации.

### app/Content/Types/TextType.php

```php
<?php
namespace App\Content\Types;

use ContentCms\Content\Types\AbstractContentBlockType;

final class TextType extends AbstractContentBlockType
{
    public function key(): string { return 'text'; }
    public function label(): string { return 'Текст'; }
    public function blockFields(): array
    {
        return [$this->text('Заголовок', 'heading'), $this->textarea('Текст', 'body')];
    }
    public function blockRules(): array
    {
        return ['heading' => ['required', 'string', 'max:255'], 'body' => ['nullable', 'string']];
    }
}
```

### app/Content/Types/CardsType.php

```php
<?php
namespace App\Content\Types;

use ContentCms\Content\Data\ContentItemGroup;
use ContentCms\Content\Types\AbstractContentBlockType;
use ContentCms\Rules\SafeContentUrl;

final class CardsType extends AbstractContentBlockType
{
    public function key(): string { return 'cards'; }
    public function label(): string { return 'Карточки'; }
    public function blockFields(): array { return [$this->text('Заголовок', 'heading')]; }
    public function blockRules(): array { return ['heading' => ['required', 'string', 'max:255']]; }
    public function itemGroups(): array
    {
        return ['cards' => new ContentItemGroup(
            key: 'cards', label: 'Карточки',
            fields: [$this->text('Название', 'label'), $this->textarea('Описание', 'description'), $this->text('Ссылка', 'link')],
            rules: ['label' => ['required', 'string'], 'description' => ['nullable', 'string'], 'link' => ['nullable', 'string', new SafeContentUrl]],
            mediaCollections: ['image'],
        )];
    }
    public function mediaCollections(): array { return ['cover']; }
    public function fileCollections(): array { return ['document']; }
}
```

Добавьте классы в существующий список `types` в `config/content-cms.php`, сохранив другие типы и настройки. Правила указываются без префикса `content.`. При использовании кеша конфигурации обновите его штатным процессом приложения.

```php
'types' => [App\Content\Types\TextType::class, App\Content\Types\CardsType::class],
```

В MoonShine сначала сохраняются основные поля блока/элемента, затем при редактировании заполняется JSON-содержимое и media. Обязательные поля содержимого проверяются на втором этапе. Во время подготовки снимите активность: загрузчик не проверяет полноту заполнения активной записи.

## 2. Маршрут, разделы и контроллер

### routes/web.php

Добавьте именованный GET-маршрут без обязательных параметров:

```php
<?php
use App\Http\Controllers\ContentPageController;
use Illuminate\Support\Facades\Route;

Route::get('/about', ContentPageController::class)->name('about');
```

Создайте через MoonShine следующие записи (ключи — пример, а не обязательные значения пакета):

| Сущность | Значения |
| --- | --- |
| Первый раздел | key=intro, route_name=about, fragment пустой, sort_order=10 |
| Второй раздел | key=services, route_name=about, fragment=services, sort_order=20 |
| Текстовый блок первого раздела | key=welcome, type=text |
| Блок второго раздела | key=catalog, type=cards |
| Элементы catalog | group_key=cards, разные key, заполненные label/description/link |
| Меню | key=main |
| Пункт меню | ссылка на второй раздел, без external_url |

Для публичного вывода включите активность записей. Разделы одной страницы имеют одинаковый `route_name`, но разные `fragment`. Пара маршрут/якорь уникальна; пустой fragment допускается только у одного раздела маршрута. Якорь хранится без `#` и должен совпасть с HTML `id` ровно один раз на странице. Имя маршрута не равно пути `/about`.

### app/Http/Controllers/ContentPageController.php

```php
<?php
namespace App\Http\Controllers;

use ContentCms\Actions\Content\LoadPublishedSections;
use ContentCms\Actions\Menu\BuildMenu;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ContentPageController
{
    public function __invoke(Request $request, LoadPublishedSections $sections, BuildMenu $menus): View
    {
        return view('content.page', [
            'sections' => $sections->handle((string) $request->route()->getName()),
            'menu' => $menus->handle('main'),
            'blockViews' => ['text' => 'content.blocks.text', 'cards' => 'content.blocks.cards'],
        ]);
    }
}
```

Карта `blockViews` — разрешённый список шаблонов приложения. Не составляйте имя view из произвольного JSON. Настройки, контакты и бизнес-вычисления передавайте дополнительными значениями контроллера: пакет не предоставляет SiteSettings. Для другого маршрута используйте тот же контроллер и разделы с соответствующим route_name.

## 3. Последовательный вывод разделов и блоков

### resources/views/content/page.blade.php

Пример без зависимости от layout. В существующем сайте перенесите содержимое main в его layout/section, сохранив загрузку данных в контроллере.

```blade
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>О нас</title></head>
<body>
    <nav aria-label="Основное меню">
        @include('content.menu', ['items' => $menu])
    </nav>
    <main>
        @forelse ($sections as $section)
            @php($fragment = ltrim(trim((string) $section->fragment), '#'))
            <section @if ($fragment !== '') id="{{ $fragment }}" @endif>
                @foreach ($section->publishedBlocks as $block)
                    @php($blockView = $blockViews[$block->type] ?? null)
                    @if (is_string($blockView) && view()->exists($blockView))
                        @include($blockView, ['block' => $block])
                    @endif
                @endforeach
            </section>
        @empty
            <p>Контент пока не опубликован.</p>
        @endforelse
    </main>
</body>
</html>
```

Разделы и блоки приходят в порядке sort_order/id. Неизвестные типы отфильтрованы загрузчиком. Зарегистрированный тип без записи в blockViews или с отсутствующим шаблоном пропускается. Если разделы есть, но в них нет выводимых блоков, пример оставляет пустые оболочки разделов; общая заглушка показывается только при отсутствии разделов.

## 4. Блоки, элементы и media

### resources/views/content/blocks/text.blade.php

```blade
<div>
    <h2>{{ $block->content['heading'] ?? '' }}</h2>
    <p style="white-space: pre-line">{{ $block->content['body'] ?? '' }}</p>
</div>
```

### resources/views/content/blocks/cards.blade.php

```blade
<div>
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
</div>
```

`?? ''` поддерживает старые записи без нового JSON-поля. `publishedItems` — уже загруженная коллекция; where здесь фильтрует в памяти. Изображения cover/image и файл document должны быть загружены через соответствующие коллекции. Пустой URL не создаёт пустой img или ссылку. Документ допускает PDF/JPEG/PNG/WebP, изображения форм — JPEG/PNG/WebP; диски и доступность файлов настраивает приложение.

Всегда экранируйте CMS-текст через `{{ }}`. Для HTML нужен отдельный согласованный процесс очистки; `{!! !!}` в этих примерах не используется. URL из JSON проверяется resolver повторно при выводе. Допустимы HTTP/HTTPS, локальные пути и якоря; обратные слеши, javascript и data URL запрещены.

## 5. Вложенное меню

### resources/views/content/menu.blade.php

```blade
@if (count($items) > 0)
    <ul>
        @foreach ($items as $entry)
            <li>
                <a href="{{ $entry['url'] }}" target="{{ $entry['target'] }}"
                   @if ($entry['rel']) rel="{{ $entry['rel'] }}" @endif>{{ $entry['label'] }}</a>
                @include('content.menu', ['items' => $entry['children']])
            </li>
        @endforeach
    </ul>
@endif
```

BuildMenu возвращает готовое дерево с безопасными целями. Для новой вкладки передаёт `_blank` и `noopener noreferrer`. Для пункта, связанного с разделом services, URL включает `/about#services`. Если подпись пункта пуста, используется title раздела.

Отсутствующее/неактивное меню даёт пустой массив. Неактивные пункты и ссылки на неактивные разделы скрыты; дети скрытого родителя не поднимаются выше. Наличие якоря в HTML зависит от вашей вёрстки — builder её не проверяет.

## 6. Размещение по ключам в существующей вёрстке

### resources/views/content/fixed.blade.php

Это альтернативный шаблон страницы. Чтобы его использовать, замените в контроллере `content.page` на `content.fixed`. Не выводите оба варианта одновременно, чтобы не дублировать якоря.

```blade
@php
    $intro = $sections->firstWhere('key', 'intro');
    $welcome = $intro?->publishedBlocks->firstWhere('key', 'welcome');
    $services = $sections->firstWhere('key', 'services');
    $catalog = $services?->publishedBlocks->firstWhere('key', 'catalog');
@endphp
<nav aria-label="Основное меню">@include('content.menu', ['items' => $menu])</nav>
<main>
    @if ($intro && $welcome && $welcome->type === 'text')
        @php($fragment = ltrim(trim((string) $intro->fragment), '#'))
        <header @if ($fragment !== '') id="{{ $fragment }}" @endif>
            @include('content.blocks.text', ['block' => $welcome])
        </header>
    @endif
    @if ($services && $catalog && $catalog->type === 'cards')
        @php($fragment = ltrim(trim((string) $services->fragment), '#'))
        <section @if ($fragment !== '') id="{{ $fragment }}" @endif>
            @include('content.blocks.cards', ['block' => $catalog])
        </section>
    @endif
</main>
```

Здесь поиск по ключам идёт в загруженных коллекциях. Не используйте ID из одной базы, названия title или `Model::query()` во view. Неактивный/удалённый блок не находится и его область пропускается. Такой способ выводит только выбранные блоки; новые блоки автоматически на страницу не добавляются. Меню может ссылаться на раздел, область которого пропущена из-за отсутствующего блока: согласуйте содержимое и навигацию при публикации.

## 7. Диагностика и проверка

| Симптом | Что проверить |
| --- | --- |
| Раздел не отображается | Имя маршрута, route_name раздела, is_active |
| Блок отсутствует | Активность, регистрацию типа, карту blockViews и наличие view |
| Карточка отсутствует | is_active элемента, group_key=cards, отношение publishedItems |
| Новое поле пустое | Оно не заполнено в старом JSON; откройте вторую стадию редактирования |
| Якорь не работает | Один HTML id, совпадающий с fragment, и фактический вывод области |
| Нет изображения/файла | Имя коллекции, наличие media, диск/URL и storage:link для public-диска |
| Нет пункта меню | Ключ меню, активность родителей и раздела, безопасный URL, непустая подпись |
| Изменение типа не видно | Обновление кеша конфигурации и перезапуск долгоживущих workers по процессу приложения |

Проверяйте результат на отдельной тестовой БД: несколько маршрутов, неактивные записи, старый JSON, отсутствующие media и шаблоны, вложенное меню и специальные символы текста. После загрузки данных контроллером рендеринг Blade не должен добавлять SQL-запросов. Используйте именно publishedBlocks/publishedItems и media, заранее загруженные LoadPublishedSections; вызовы blocks(), items() или свежие запросы в Blade нарушают это условие.

Руководство не требует установленного skill и не содержит бизнес-логики конкретного сайта. Публикуется командой `php artisan content-cms:publish-guideline`; для замены существующего документа используйте `--force` после проверки локальных правок.


## 8. Общие области: шапка и подвал (2.0)

Сначала сгенерируйте локальную админку через `content-cms:publish-moonshine` и подключите выведенный командой provider, если у приложения ещё нет собственных форм. Типы, карта шаблонов и views остаются приложению.

Создайте активный раздел с key `header`, оставив маршрут пустым. Пустой маршрут сохраняется как NULL, fragment очищается. Можно создать несколько таких разделов с разными ключами. Они не имеют URL и не являются целями меню; для ссылки используйте раздел страницы либо проверенный локальный путь.

В существующем контроллере дополнительно загрузите общую область:

```php
$header = app(\ContentCms\Actions\Content\LoadPublishedSectionByKey::class)->handle('header');
// Передайте $header в view вместе с sections, menu, blockViews и настройками приложения.
```

В layout приложения выводите только разрешённые типы через уже переданную карту:

```blade
<header>
    @foreach ($header?->publishedBlocks ?? [] as $block)
        @php($template = $blockViews[$block->type] ?? null)
        @if ($template && view()->exists($template))
            @include($template, ['block' => $block])
        @endif
    @endforeach
</header>
```

При отсутствии/неактивности раздела загрузчик возвращает null. Блоки, элементы и media заранее загружены, включая связь элемента с его блоком для media collections; не вызывайте запросы отношений во view. Загрузчик по ключу работает также для разделов с маршрутом. Общие области не добавляются автоматически в LoadPublishedSections, поэтому шапку не приходится исключать из основного цикла страницы.
