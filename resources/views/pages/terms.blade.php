<x-layouts.site :title="__('terms.title')">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="mb-6 break-words font-display text-heading-m uppercase text-ink-900">{{ __('terms.title') }}</h1>
        @forelse ($termsHtml as $html)
            <x-content.terms-text :html="$html" />
        @empty
            <p class="text-body-m text-ink-500">{{ __('terms.empty') }}</p>
        @endforelse
    </div>
</x-layouts.site>
