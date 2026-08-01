@props(['title' => null])
<x-layouts.app :title="$title">
    <main id="main" class="min-h-full flex items-center justify-center px-6 py-16">
        <div class="w-full max-w-auth">
            {{ $slot }}
        </div>
    </main>
</x-layouts.app>
