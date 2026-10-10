@props(['title' => null])
    <!doctype html>
{{-- scroll-pt-24 оставляет место под закреплённую шапку при переходе к якорям, включая #main и ссылки CMS-меню.
   Отступ рассчитан на мобильную и широкую шапку. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-pt-24">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @php
        $seoLookupUrl = request()->getRequestUri();
        $seoMetadata = app(\Dimarik\LaravelSeo\SeoManager::class)->forPage(
            new \Dimarik\LaravelSeo\SeoPage(
                lookupUrl: $seoLookupUrl,
                canonical: request()->url(),
                title: $title,
            ),
        );
        $seoText = \Dimarik\LaravelSeo\Models\Seo::query()
            ->whereIn('url', array_unique([$seoLookupUrl, parse_url($seoLookupUrl, PHP_URL_PATH) ?: '/']))
            ->orderByRaw('CASE WHEN url = ? THEN 0 ELSE 1 END', [$seoLookupUrl])
            ->first(['text'])?->text;
    @endphp
    <x-laravel-seo::meta :metadata="$seoMetadata" />
    @unless (config('seo.indexable'))
        <meta name="robots" content="noindex, nofollow">
    @endunless
    {{-- text — сырой HTML-блок (OG-теги + JSON-LD), собранный и уже
         экранированный на этапе генерации (Domain\Catalog\Actions\SyncProductSeoAction) —
         {!! !!}, не {{ }}: это готовая доверенная разметка, а не пользовательский ввод. --}}
    @if ($seoText)
        {!! $seoText !!}
    @endif

    {{-- SDK Telegram Mini App: даёт window.Telegram.WebApp.initData. Грузится
         синхронно и до @vite — resources/js/telegram.js читает его сразу на
         alpine:init, скрипт должен успеть отработать раньше. Безвредно вне
         Mini App: объект создаётся, initData там просто пустая строка. --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-cream-100 text-ink-900 antialiased">
<a href="#main"
   class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-sm focus:bg-teal-700 focus:px-4 focus:py-2 focus:text-cream-100">
    {{ __('ui.skip_to_content') }}
</a>

{{-- На всех страницах (не только /login), чтобы гость, открывший Mini App
     на каталоге/товаре/корзине, входил без перехода на форму входа. --}}
<x-ui.telegram-autologin/>

{{ $slot }}
</body>
</html>
