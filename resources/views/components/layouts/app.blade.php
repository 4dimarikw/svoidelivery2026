@props(['title' => null])
    <!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-cream-100 text-ink-900 antialiased">
<a href="#main"
   class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-sm focus:bg-teal-700 focus:px-4 focus:py-2 focus:text-cream-100">
    {{ __('ui.skip_to_content') }}
</a>

{{ $slot }}
</body>
</html>
