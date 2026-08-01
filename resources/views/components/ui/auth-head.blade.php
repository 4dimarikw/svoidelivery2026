{{-- Renders <h1> — the design system's own markup uses <h3> because the
     mockup is one long document with its own heading hierarchy; a real auth
     page's title is the top-level heading. --}}
@props([
    'title',
    'subtitle' => null,
    'logo' => true,
])

<div class="mb-6.5 text-center">
    @if ($logo)
        <img src="{{ asset('img/logo.png') }}" alt="{{ config('app.name') }}" class="mx-auto h-18 w-18 object-contain">
    @endif
    <h1 class="mt-3 font-display text-auth-title font-bold uppercase text-ink-900">{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-caption text-ink-500">{{ $subtitle }}</p>
    @endif
</div>
