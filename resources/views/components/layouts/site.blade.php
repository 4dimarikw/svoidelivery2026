{{-- For public content pages (header + footer chrome). NOT used by
     <x-layouts.auth> — auth pages stay a clean, chrome-free centered card,
     which wraps <x-layouts.app> directly instead.

     pb-20 leaves room for <x-ui.mobile-nav> (fixed, md:hidden) so it never
     covers the footer on small screens; md:pb-0 drops that once the bar
     itself is hidden. --}}
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
