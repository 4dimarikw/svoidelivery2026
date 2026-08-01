{{-- For public content pages (header + footer chrome). NOT used by
     <x-layouts.auth> — auth pages stay a clean, chrome-free centered card,
     which wraps <x-layouts.app> directly instead. --}}
@props(['title' => null])
<x-layouts.app :title="$title">
    <div class="flex min-h-full flex-col">
        <x-layouts.header />

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <x-layouts.footer />
    </div>
</x-layouts.app>
