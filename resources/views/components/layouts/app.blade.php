@props(['title' => null])
    <!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <title>{{ (seo()->meta()->title() ?? $title  ?: config('app.name')) }}</title>

    @if ($seoDescription = seo()->meta()->description())
        <meta name="description" content="{{ $seoDescription }}">
    @endif
    @if ($seoKeywords = seo()->meta()->keywords())
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    {{-- text — сырой HTML-блок (OG-теги + JSON-LD), собранный и уже
         экранированный на этапе генерации (Domain\Catalog\Actions\SyncProductSeoAction) —
         {!! !!}, не {{ }}: это готовая доверенная разметка, а не пользовательский ввод. --}}
    @if ($seoText = seo()->meta()->text())
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
