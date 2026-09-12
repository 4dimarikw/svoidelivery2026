{{-- Заголовок страницы аутентификации имеет уровень h1. --}}
@props([
    'title',
    'subtitle' => null,
    'logo' => true,
])

<div class="mb-6.5 text-center">
    @if ($logo)
        <img src="{{ asset('img/logo.png') }}" alt="{{ config('app.name') }}" class="mx-auto h-18 w-18 object-contain">
    @endif
    <h1 class="mt-3 break-words font-display text-heading-s font-bold uppercase text-ink-900 sm:text-auth-title">{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-caption text-ink-500">{{ $subtitle }}</p>
    @endif
</div>
