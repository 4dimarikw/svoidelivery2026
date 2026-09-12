{{-- Макет публичных страниц с шапкой и подвалом. pb-20 оставляет место под фиксированную мобильную навигацию; от
   md отступ убирается. Страницы входа используют отдельный макет auth. --}}
@props(['title' => null])
<x-layouts.app :title="$title">
    <div class="flex min-h-full flex-col pb-20 md:pb-0">
        <x-layouts.header />

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <x-layouts.footer />
    </div>

    <x-ui.mobile-nav />
</x-layouts.app>
