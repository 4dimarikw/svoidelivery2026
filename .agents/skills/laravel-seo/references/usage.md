# Метаданные, вывод и MoonShine

## Получение метаданных

`SeoManager::forPage(SeoPage $page, ?SeoDefaults $defaults = null): SeoMetadata` ищет запись одним запросом по адресу со строкой запроса и по пути без неё.

```php
use Dimarik\LaravelSeo\SeoDefaults;
use Dimarik\LaravelSeo\SeoManager;
use Dimarik\LaravelSeo\SeoPage;

$metadata = app(SeoManager::class)->forPage(
    new SeoPage(
        lookupUrl: $request->getRequestUri(), // путь со строкой запроса
        canonical: route('catalog'),          // выбирает приложение
        title: 'Catalog',
        description: 'All products',
    ),
    new SeoDefaults(title: config('app.name')),
);
```

- `lookupUrl` – путь со строкой запроса для поиска записи; `canonical` – адрес, который приложение считает каноническим, записи его не переопределяют.
- Значения полей: запись → `SeoPage` → `SeoDefaults` (без второго аргумента – `seo.default.*`) → `app.name` для заголовка. Значения двух записей-кандидатов не смешиваются.
- Кэша между запросами нет: изменения записи видны в следующем запросе.

Open Graph, Twitter, JSON-LD и `article:published_time` добавляют созданием нового `SeoMetadata` с полями `openGraph`, `twitter`, `structuredData`, `publishedTime` на основе результата `forPage()`. Не помещай в `structuredData` непроверенный HTML: компонент кодирует JSON-LD с `JSON_HEX_*`, но содержимое отвечает приложению.

## Вывод

```blade
<head>
    <x-laravel-seo::meta :metadata="$seoMetadata" />
</head>
```

Перед подключением найди в layout и шаблонах существующие `<title>`, description, canonical, keywords, robots, `og:*`, `twitter:*`, JSON-LD и убери дубли, сохранив данные, которые теперь должны прийти через `SeoMetadata`. Поле `text` записи компонент не выводит: если сайт показывает SEO-текст, выводи его в шаблоне страницы отдельно и экранируй.

## View composer

Используй composer, чтобы каждая страница получала `$seoMetadata`. Пример `SeoComposer` есть в README пакета (раздел о передаче метаданных на все страницы): он не трогает готовый `seoMetadata`, берёт `seoPage` из данных представления или строит `SeoPage` из текущего запроса.

- Для `@extends('layouts.app')` регистрируй composer на layout: `View::composer('layouts.app', SeoComposer::class)`.
- Для компонента-макета (`<x-app-layout>`) данные дочернего представления в макет не попадают: регистрируй composer на представления страниц и передавай `$seoMetadata` в компонент явно.
- Контроллеры, которым нужны свои значения, передают `seoPage` или готовый `seoMetadata`.

Проверь страницы с параметрами запроса (пагинация, фильтры): запись для `/catalog?page=2` имеет приоритет над `/catalog`, а canonical остаётся выбором приложения.

## Валидация адресов

Для полей адреса в формах приложения используй `Dimarik\LaravelSeo\SeoPathRule` и уникальность по таблице `seo`. Модель нормализует `url` при присваивании: `https://example.com/a?x=1#top` хранится как `/a?x=1`, нелокальные значения дают `ValidationException` по ключу `url`.

## MoonShine

Если в приложении установлен MoonShine 4.19.3+, сгенерируй ресурсы:

```bash
php artisan seo:moonshine-stubs --namespace='App\MoonShine\Resources\Seo' --no-interaction
```

Команда пишет `SeoResource`, `SeoFields` и страницы списка, формы, просмотра в каталог пространства имён внутри `app/`, останавливается при существующих файлах (`--force` перезаписывает) и не трогает провайдеры, меню и политики. Затем:

1. Добавь ресурс в провайдер MoonShine: `->resources([SeoResource::class])`.
2. Добавь пункт меню `MenuItem::make(SeoResource::class)` в существующую структуру меню.
3. Создай и зарегистрируй политику для `Dimarik\LaravelSeo\Models\Seo` по правилам доступа приложения. Ресурс использует `$withPolicy = true`: без политики все действия запрещены.

Сгенерированные файлы принадлежат приложению и при обновлении пакета не меняются. Перед `--force` сравни их с заготовками в `vendor/dimarik/laravel-seo/stubs/moonshine`, чтобы не потерять правки. Проверь в панели список, поиск, создание и редактирование под пользователем с правами и отказ без прав.
