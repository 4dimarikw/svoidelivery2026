# Установка, настройка и индексация

## Совместимость и Composer

Пакет рассчитан на PHP `^8.3` и Laravel 12 или 13. Перед установкой проверь версии в `composer.json` приложения. Пакет распространяется из закрытого репозитория GitHub:

```bash
composer config repositories.laravel-seo vcs https://github.com/4dimarikw/laravel-seo.git
composer require dimarik/laravel-seo:^1.1
```

Composer нужен доступ на чтение к `4dimarikw/laravel-seo` на машинах разработчиков, в CI и на серверах: fine-grained token с правом **Contents: read** (`composer config --global github-oauth.github.com <token>`, в CI – переменная `COMPOSER_AUTH` из секрета) или deploy key с SSH-адресом репозитория. Не коммить токен и проектный `auth.json`.

Для обновления используй `composer update dimarik/laravel-seo`, затем проверь версию через `composer show dimarik/laravel-seo` и сравни `CHANGELOG.md` пакета. Провайдер `Dimarik\LaravelSeo\LaravelSeoServiceProvider` обнаруживается через package discovery; если discovery отключён, добавь его в `bootstrap/providers.php`.

## Публикация и таблица

```bash
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Таблица `seo` автоматически не создаётся. Если в приложении уже есть таблица `seo`, сравни её схему с миграцией пакета (`id`, `url` string(255) с уникальным индексом `seo_url_unique`, `title`, nullable `description`, `keywords`, `text`, timestamps) и не публикуй миграцию повторно. При расхождении подготовь миграцию приложения и согласуй перенос данных с пользователем. Существующие адреса должны проходить `SeoUrl::forStorage()`: проверь абсолютные URL, пробелы, фрагменты `#` и длину до 255 символов перед импортом.

Другие теги: `seo-views` (компонент для правки разметки), `seo-lang` (переводы `en`, `ru`). Не публикуй представления без необходимости: опубликованная копия не получает исправлений пакета. Не используй `--force` для конфигурации без сравнения с `vendor/dimarik/laravel-seo/config/seo.php`.

## Конфигурация

Ключи `config/seo.php`:

- `indexable` (`SEO_INDEXABLE`, по умолчанию `false`) – разрешает индексацию сайта;
- `title_brand` – суффикс ` | Brand`, добавляется, если заголовок не содержит бренд отдельным словом;
- `default.title`, `default.description`, `default.keywords` – значения по умолчанию, когда их не дали запись и страница;
- `noindex_routes` – шаблоны имён маршрутов, получающих `noindex` при индексируемом сайте (`'account.*'`, `'cart.*'`, `'login'`);
- `robots.enabled` – регистрирует `GET /robots.txt` (маршрут `seo.robots`);
- `robots.sitemap_url` (`SEO_SITEMAP_URL`) – абсолютный адрес sitemap, только строкой.

Добавь `SEO_INDEXABLE` и при необходимости `SEO_SITEMAP_URL` в `.env.example`. Значение `true` ставь только для production по решению пользователя.

## Индексация

Зарегистрируй middleware первым и пропусти через него ответы исключений, иначе страницы ошибок останутся без заголовка:

```php
// bootstrap/app.php
use Dimarik\LaravelSeo\Http\Middleware\PreventSiteIndexing;
use Symfony\Component\HttpFoundation\Response;

->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(PreventSiteIndexing::class);
})
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->respond(fn (Response $response) => app(PreventSiteIndexing::class)->apply(request(), $response));
})
```

Если в `withExceptions` уже есть `respond()`, объедини обработку в один callback, сохранив существующую логику. Найди в приложении собственные `X-Robots-Tag`, meta robots и middleware с той же задачей и согласуй, что оставить.

## robots.txt

Ответ пакета всегда разрешает обход (`User-agent: *`, `Disallow:`); запрет индексации обеспечивает `X-Robots-Tag`. Строка `Sitemap:` добавляется только при `indexable = true` и абсолютном http(s) `sitemap_url`. Перед включением `robots.enabled` найди маршрут `/robots.txt` и файл `public/robots.txt`: веб-сервер отдаёт статический файл раньше Laravel. Удаляй их только по согласованию с пользователем. Пакет не генерирует sitemap.

## Проверка

1. `php artisan config:cache` и `php artisan route:cache` выполняются без ошибок.
2. При `SEO_INDEXABLE=false` ответы `/`, редиректы и 404 содержат `X-Robots-Tag: noindex, nofollow` (`curl -sI <url> | grep -i x-robots-tag`).
3. При `SEO_INDEXABLE=true` обычные страницы без `X-Robots-Tag`, маршруты из `noindex_routes` – с `noindex`.
4. `/robots.txt` возвращает ответ пакета, `Sitemap:` – только на индексируемом сайте.
5. В `<head>` ровно по одному `<title>`, `<meta name="description">`, `<link rel="canonical">`.
6. Временная запись `Seo::create(['url' => '/', 'title' => 'SEO check'])` меняет заголовок главной; удали её после проверки.

Для feature-тестов приложения проверяй заголовки ответов и вывод компонента; таблица `seo` создаётся опубликованной миграцией в обычном `RefreshDatabase`.
