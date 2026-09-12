@props(['title' => null])
<x-layouts.app :title="$title">
    <main id="main" class="min-h-full flex items-center justify-center px-2 py-8 sm:px-6 sm:py-16">
        <div class="min-w-0 w-full max-w-auth">
            {{ $slot }}
        </div>
    </main>
</x-layouts.app>
