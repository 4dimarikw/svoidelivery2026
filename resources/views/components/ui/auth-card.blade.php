@props([
    'title' => null,
    'subtitle' => null,
    'logo' => true,
])

<div {{ $attributes->class('mx-auto max-w-auth rounded-sm border border-hairline bg-cream-50 px-8.5 py-9') }}>
    @if (isset($head))
        {{ $head }}
    @elseif ($title)
        <x-ui.auth-head :title="$title" :subtitle="$subtitle" :logo="$logo" />
    @endif

    {{ $slot }}

    @if (isset($footer))
        <div class="mt-6.5 text-center">
            {{ $footer }}
        </div>
    @endif
</div>
